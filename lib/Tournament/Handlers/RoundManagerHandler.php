<?php

declare(strict_types=1);

namespace Tournament\Handlers;

use Exception;

/**
 * Gestión de rondas: comprobación de mesas pendientes y generación de la siguiente ronda.
 */
final class RoundManagerHandler
{
    private function __construct()
    {
    }

    /**
     * Cuenta mesas de una ronda sin resultados registrados (misma consulta que en torneo_gestion).
     */
    public static function contarMesasIncompletas(int $torneoId, int $ronda): int
    {
        $pdo = \DB::pdo();

        $sql = "SELECT COUNT(DISTINCT pr.mesa) as mesas_incompletas
            FROM partiresul pr
            WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa > 0
            AND (pr.registrado = 0 OR pr.registrado IS NULL)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$torneoId, $ronda]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($result['mesas_incompletas'] ?? 0);
    }

    /**
     * Última ronda con partidas y si se puede generar la siguiente (sin mesas abiertas en esa ronda).
     *
     * @return array{ultima_ronda: int, mesas_incompletas: int, puede_generar_ronda: bool}
     */
    public static function verificarMesasPendientes(int $torneoId): array
    {
        $pdo = \DB::pdo();
        $stmt = $pdo->prepare('SELECT MAX(partida) as u FROM partiresul WHERE id_torneo = ?');
        $stmt->execute([$torneoId]);
        $ultima_ronda = (int) $stmt->fetchColumn();

        $mesas_incompletas = 0;
        $puede_generar = true;
        if ($ultima_ronda > 0) {
            $mesas_incompletas = self::contarMesasIncompletas($torneoId, $ultima_ronda);
            $puede_generar = $mesas_incompletas === 0;
        }

        return [
            'ultima_ronda' => $ultima_ronda,
            'mesas_incompletas' => $mesas_incompletas,
            'puede_generar_ronda' => $puede_generar,
        ];
    }

    /**
     * Genera la siguiente ronda (emparejamientos, mesas, inserts). Sin verificar permisos: el llamador debe hacerlo.
     */
    public static function ejecutarGeneracionRonda(int $torneoId): void
    {
        try {
            $pdo = \DB::pdo();

            // Solo estatus 1 (confirmado) cuentan para participar en el torneo
            require_once __DIR__ . '/../../InscritosHelper.php';
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM inscritos WHERE torneo_id = ? AND ' . InscritosHelper::SQL_WHERE_SOLO_CONFIRMADO);
            $stmt->execute([$torneoId]);
            $num_inscritos = (int) $stmt->fetchColumn();
            if ($num_inscritos < 4) {
                $_SESSION['error'] = 'No se puede generar ronda: se necesitan al menos 4 participantes inscritos y activos en el torneo. Actualmente hay ' . $num_inscritos . '.';
                header('Location: ' . buildRedirectUrl('panel', ['torneo_id' => $torneoId]));
                exit;
            }

            // Obtener torneo para verificar modalidad y nombre
            $stmt = $pdo->prepare('SELECT nombre, rondas, modalidad FROM tournaments WHERE id = ?');
            $stmt->execute([$torneoId]);
            $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
            $total_rondas = (int) ($torneo['rondas'] ?? 0);
            $modalidad = (int) ($torneo['modalidad'] ?? 0);

            // Determinar qué servicio usar según modalidad (3 = Equipos)
            $es_torneo_equipos = ($modalidad === 3);

            if ($es_torneo_equipos) {
                require_once __DIR__ . '/../../../config/MesaAsignacionEquiposService.php';
                $mesaService = new \MesaAsignacionEquiposService();
            } else {
                require_once __DIR__ . '/../../../config/MesaAsignacionService.php';
                $mesaService = new \MesaAsignacionService();
            }

            // Verificar que la última ronda esté completa
            $ultima_ronda = $mesaService->obtenerUltimaRonda($torneoId);

            if ($ultima_ronda > 0) {
                $todas_completas = $mesaService->todasLasMesasCompletas($torneoId, $ultima_ronda);
                if (!$todas_completas) {
                    $mesas_incompletas = $mesaService->contarMesasIncompletas($torneoId, $ultima_ronda);
                    $_SESSION['error'] = "No se puede generar una nueva ronda. Faltan resultados en {$mesas_incompletas} mesa(s) de la ronda {$ultima_ronda}";
                    header('Location: ' . buildRedirectUrl('panel', ['torneo_id' => $torneoId]));
                    exit;
                }
            }

            // Actualizar estadísticas antes de generar nueva ronda
            try {
                actualizarEstadisticasInscritos($torneoId);
            } catch (Exception $e) {
                $_SESSION['error'] = 'Error al actualizar estadísticas: ' . $e->getMessage();
                header('Location: ' . buildRedirectUrl('panel', ['torneo_id' => $torneoId]));
                exit;
            }

            $proxima_ronda = $ultima_ronda + 1;
            $msg_no_presentes = '';

            // Antes de generar la 3.ª ronda: marcar como retirados a los no presentes (pendientes sin ninguna partida)
            if ($proxima_ronda === 3) {
                $marcados_retirados = marcarNoPresentesRetiradosAntesRonda3($torneoId);
                if ($marcados_retirados > 0) {
                    $msg_no_presentes = $marcados_retirados . ' inscrito(s) no presente(s) marcado(s) como retirado(s).';
                }
                // Revalidar que sigan habiendo al menos 4 confirmados tras retirar no presentes
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM inscritos WHERE torneo_id = ? AND ' . InscritosHelper::SQL_WHERE_SOLO_CONFIRMADO);
                $stmt->execute([$torneoId]);
                if ((int) $stmt->fetchColumn() < 4) {
                    $_SESSION['error'] = 'No se puede generar la ronda 3: tras marcar no presentes quedan menos de 4 participantes confirmados.';
                    header('Location: ' . buildRedirectUrl('panel', ['torneo_id' => $torneoId]));
                    exit;
                }
            }

            // Obtener estrategia de asignación (para equipos puede ser: secuencial, intercalada_13_24, intercalada_14_23, por_rendimiento)
            if ($es_torneo_equipos) {
                $estrategia = $_POST['estrategia_asignacion'] ?? 'secuencial';
            } else {
                $estrategia = $_POST['estrategia_ronda2'] ?? 'separar';
            }

            // Generar ronda usando el servicio apropiado
            if ($es_torneo_equipos) {
                $resultado = $mesaService->generarAsignacionRonda(
                    $torneoId,
                    $proxima_ronda,
                    $total_rondas,
                    $estrategia
                );
            } else {
                $resultado = $mesaService->generarAsignacionRonda(
                    $torneoId,
                    $proxima_ronda,
                    $total_rondas,
                    $estrategia
                );
            }

            if ($resultado['success']) {
                $mensaje = $resultado['message'];
                if (isset($resultado['total_mesas'])) {
                    $mensaje .= ': ' . $resultado['total_mesas'] . ' mesas';
                }
                if (isset($resultado['total_equipos'])) {
                    $mensaje .= ', ' . $resultado['total_equipos'] . ' equipos';
                }
                if (isset($resultado['jugadores_bye']) && $resultado['jugadores_bye'] > 0) {
                    $mensaje .= ', ' . $resultado['jugadores_bye'] . ' jugadores BYE';
                }
                if ($msg_no_presentes !== '') {
                    $mensaje .= '. ' . $msg_no_presentes;
                }
                $_SESSION['success'] = $mensaje;

                // Encolar notificaciones masivas (Telegram + campanita web) usando plantilla 'nueva_ronda'
                try {
                    $stmtJug = $pdo->prepare("
                    SELECT u.id, u.nombre, u.telegram_chat_id,
                           COALESCE(i.posicion, 0) AS posicion, COALESCE(i.ganados, 0) AS ganados, COALESCE(i.perdidos, 0) AS perdidos,
                           COALESCE(i.efectividad, 0) AS efectividad, COALESCE(i.puntos, 0) AS puntos
                    FROM inscritos i
                    INNER JOIN usuarios u ON i.id_usuario = u.id
                    WHERE i.torneo_id = ? AND " . InscritosHelper::sqlWhereSoloConfirmadoConAlias('i') . '
                ');
                    $stmtJug->execute([$torneoId]);
                    $jugadores = $stmtJug->fetchAll(PDO::FETCH_ASSOC);

                    // Mesa y pareja para esta ronda (partiresul ya tiene la asignación recién generada)
                    $mesaPareja = [];
                    $stmtMesa = $pdo->prepare("
                    SELECT pr.id_usuario, pr.mesa, pr_p.id_usuario AS pareja_id, u_pareja.nombre AS pareja_nombre
                    FROM partiresul pr
                    LEFT JOIN partiresul pr_p ON pr_p.id_torneo = pr.id_torneo AND pr_p.partida = pr.partida AND pr_p.mesa = pr.mesa
                        AND pr_p.secuencia = CASE pr.secuencia WHEN 1 THEN 2 WHEN 2 THEN 1 WHEN 3 THEN 4 WHEN 4 THEN 3 END
                    LEFT JOIN usuarios u_pareja ON u_pareja.id = pr_p.id_usuario
                    WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa > 0
                ");
                    $stmtMesa->execute([$torneoId, $proxima_ronda]);
                    while ($row = $stmtMesa->fetch(PDO::FETCH_ASSOC)) {
                        $mesaPareja[(int) $row['id_usuario']] = [
                            'mesa' => (string) $row['mesa'],
                            'pareja_id' => (int) ($row['pareja_id'] ?? 0),
                            'pareja' => trim((string) ($row['pareja_nombre'] ?? '')) ?: '—',
                        ];
                    }

                    require_once __DIR__ . '/../../app_helpers.php';
                    foreach ($jugadores as &$j) {
                        $uid = (int) $j['id'];
                        $j['mesa'] = $mesaPareja[$uid]['mesa'] ?? '—';
                        $j['pareja_id'] = $mesaPareja[$uid]['pareja_id'] ?? 0;
                        $j['pareja'] = $mesaPareja[$uid]['pareja'] ?? '—';
                        $j['url_resumen'] = \AppHelpers::url('index.php', ['page' => 'torneo_gestion', 'action' => 'resumen_individual', 'torneo_id' => $torneoId, 'inscrito_id' => $uid, 'from' => 'notificaciones']);
                        $j['url_clasificacion'] = \AppHelpers::url('index.php', ['page' => 'torneo_gestion', 'action' => 'posiciones', 'torneo_id' => $torneoId, 'from' => 'notificaciones']);
                    }
                    unset($j);

                    $titulo = $torneo['nombre'] ?? 'Torneo';
                    if (!empty($jugadores)) {
                        require_once __DIR__ . '/../../NotificationManager.php';
                        $nm = new \NotificationManager($pdo);
                        $nm->programarRondaMasiva($jugadores, $titulo, $proxima_ronda, null, 'nueva_ronda', $torneoId);
                    }
                } catch (Exception $e) {
                    error_log('Notificaciones ronda: ' . $e->getMessage());
                }
            } else {
                $_SESSION['error'] = $resultado['message'];
            }
        } catch (Exception $e) {
            error_log('Error al generar ronda: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            $_SESSION['error'] = 'Error al generar ronda: ' . $e->getMessage();
        }

        // Permanecer siempre en el panel: éxito o error. El usuario irá al formulario de resultados cuando lo requiera.
        if (isset($torneoId) && $torneoId > 0) {
            header('Location: ' . buildRedirectUrl('panel', ['torneo_id' => $torneoId]));
            exit;
        }

        header('Location: ' . buildRedirectUrl('panel', ['torneo_id' => $torneoId]));
        exit;
    }
}
