<?php

declare(strict_types=1);

/**
 * Capa de datos para la vista del panel de torneo (panel-moderno).
 * Centraliza consultas PDO, flags de negocio y derivados que antes estaban en obtenerDatosPanel()
 * La vista panel-moderno.php consume solo extract($view_data) + fallback mínimo de URL/sesión.
 */
final class PanelTorneoViewData
{
    /**
     * Construye el array $view_data para extract() en la vista.
     *
     * @throws RuntimeException si el torneo no existe en BD
     * @return array<string, mixed>
     */
    public static function build(int $torneoId): array
    {
        require_once __DIR__ . '/../config/db.php';
        require_once __DIR__ . '/InscritosHelper.php';
        require_once __DIR__ . '/../config/MesaAsignacionService.php';

        self::ensureCorreccionesCierreColumn();

        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT * FROM tournaments WHERE id = ?');
        $stmt->execute([$torneoId]);
        $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($torneo === false || $torneo === null) {
            throw new RuntimeException('Torneo no encontrado');
        }

        $rondas_generadas = self::fetchRondasGeneradas($pdo, $torneoId);
        $ultima_ronda = !empty($rondas_generadas)
            ? (int) max(array_column($rondas_generadas, 'num_ronda'))
            : 0;
        $proxima_ronda = $ultima_ronda + 1;

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM inscritos WHERE torneo_id = ?');
        $stmt->execute([$torneoId]);
        $total_inscritos = (int) $stmt->fetchColumn();

        $sqlConfirmados = 'SELECT COUNT(*) FROM inscritos WHERE torneo_id = ? AND (' . InscritosHelper::SQL_WHERE_SOLO_CONFIRMADO . ')';
        $stmt = $pdo->prepare($sqlConfirmados);
        $stmt->execute([$torneoId]);
        $inscritos_confirmados = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM partiresul WHERE id_torneo = ? AND registrado = 1');
        $stmt->execute([$torneoId]);
        $total_partidas = (int) $stmt->fetchColumn();

        $puede_generar = true;
        $mesas_incompletas = 0;
        $total_mesas_ronda = 0;
        $ultima_ronda_tiene_resultados = false;

        if ($ultima_ronda > 0) {
            $mesas_incompletas = self::countMesasIncompletas($pdo, $torneoId, $ultima_ronda);
            $puede_generar = $mesas_incompletas === 0;

            $stmt = $pdo->prepare(
                'SELECT COUNT(DISTINCT mesa) FROM partiresul WHERE id_torneo = ? AND partida = ? AND mesa > 0'
            );
            $stmt->execute([$torneoId, $ultima_ronda]);
            $total_mesas_ronda = (int) $stmt->fetchColumn();

            $mesaService = new MesaAsignacionService();
            $ultima_ronda_tiene_resultados = $mesaService->rondaTieneResultadosEnMesas($torneoId, $ultima_ronda);
        }

        $correcciones_cierre_at = $torneo['correcciones_cierre_at'] ?? null;
        if ($correcciones_cierre_at === '' || $correcciones_cierre_at === '0000-00-00 00:00:00') {
            $correcciones_cierre_at = null;
        }

        $organizacion_nombre = 'N/A';
        $organizacion_logo = null;
        if (!empty($torneo['club_responsable'])) {
            $stmt = $pdo->prepare('SELECT nombre, logo FROM organizaciones WHERE id = ?');
            $stmt->execute([(int) $torneo['club_responsable']]);
            $org = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($org) {
                $organizacion_nombre = $org['nombre'] ?? 'N/A';
                $organizacion_logo = !empty($org['logo']) ? $org['logo'] : null;
            }
        }
        $torneo['organizacion_nombre'] = $organizacion_nombre;
        $torneo['organizacion_logo'] = $organizacion_logo;

        $actas_pendientes_count = self::countActasPendientesVerificacion($pdo, $torneoId);
        [$mesas_verificadas_count, $mesas_digitadas_count] = self::countMesasAuditoria($pdo, $torneoId);

        $total_equipos = 0;
        $total_jugadores_inscritos = 0;
        if ((int) ($torneo['modalidad'] ?? 0) === 3) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM equipos WHERE id_torneo = ?');
            $stmt->execute([$torneoId]);
            $total_equipos = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM inscritos WHERE torneo_id = ? AND codigo_equipo IS NOT NULL AND codigo_equipo != \'\' AND codigo_equipo != \'000-000\' AND ('
                . InscritosHelper::SQL_WHERE_SOLO_CONFIRMADO . ')'
            );
            $stmt->execute([$torneoId]);
            $total_jugadores_inscritos = (int) $stmt->fetchColumn();
        }

        $modalidad_num = (int) ($torneo['modalidad'] ?? 0);
        $es_modalidad_equipos = $modalidad_num === 3;
        $es_modalidad_parejas = $modalidad_num === 2;
        $es_modalidad_parejas_fijas = $modalidad_num === 4;
        $es_modalidad_equipos_o_parejas = $es_modalidad_equipos || $es_modalidad_parejas;

        $label_modalidad = self::labelModalidad($modalidad_num);
        $podios_action = $es_modalidad_equipos ? 'podios_equipos' : 'podios';

        $torneo_bloqueado_inscripciones = false;
        if ($ultima_ronda > 0) {
            $torneo_bloqueado_inscripciones = ($es_modalidad_equipos || $es_modalidad_parejas || $es_modalidad_parejas_fijas)
                ? ($ultima_ronda >= 1)
                : ($ultima_ronda >= 2);
        }

        $totalRondas = isset($torneo['rondas']) ? (int) $torneo['rondas'] : 0;
        $isLocked = isset($torneo['locked']) && (int) $torneo['locked'] === 1;

        $mesasInc = $mesas_incompletas;
        $torneo_completado = $totalRondas > 0 && $ultima_ronda >= $totalRondas && $mesasInc === 0;
        $puedeCerrar = !$isLocked && $ultima_ronda > 0 && $mesasInc === 0 && $torneo_completado;

        $countdown_fin_timestamp = null;
        $mostrar_aviso_20min = false;
        if (!empty($correcciones_cierre_at)) {
            $ts = strtotime((string) $correcciones_cierre_at);
            if ($ts !== false) {
                $countdown_fin_timestamp = $ts;
                $mostrar_aviso_20min = !$isLocked && $torneo_completado && (time() < $ts);
            }
        }

        $inscritos_para_rondas = $inscritos_confirmados;
        $estadisticas = [
            'confirmados' => $inscritos_confirmados,
            'solventes' => 0,
            'total_partidas' => $total_partidas,
            'mesas_ronda' => $total_mesas_ronda,
            'total_equipos' => $total_equipos,
            'total_jugadores_inscritos' => $total_jugadores_inscritos,
        ];

        $primera_mesa = self::fetchPrimeraMesaUltimaRonda($pdo, $torneoId, $ultima_ronda);

        $tiempo_ronda_min = (int) ($torneo['tiempo'] ?? 35);
        if ($tiempo_ronda_min < 1) {
            $tiempo_ronda_min = 35;
        }

        $nombreTorneo = (string) ($torneo['nombre'] ?? 'Torneo');
        $page_title = 'Panel de Control - ' . htmlspecialchars($nombreTorneo, ENT_QUOTES, 'UTF-8');

        return [
            'torneo' => $torneo,
            'torneo_id' => $torneoId,
            'rondas' => $rondas_generadas,
            'rondas_generadas' => $rondas_generadas,
            'ultimaRonda' => $ultima_ronda,
            'ultima_ronda' => $ultima_ronda,
            'proximaRonda' => $proxima_ronda,
            'proxima_ronda' => $proxima_ronda,
            'totalInscritos' => $total_inscritos,
            'total_inscritos' => $total_inscritos,
            'inscritos_confirmados' => $inscritos_confirmados,
            'inscritos_para_rondas' => $inscritos_para_rondas,
            'total_equipos' => $total_equipos,
            'total_jugadores_inscritos' => $total_jugadores_inscritos,
            'puedeGenerarRonda' => $puede_generar,
            'puede_generar_ronda' => $puede_generar,
            'puedeGenerar' => $puede_generar,
            'mesasIncompletas' => $mesas_incompletas,
            'mesas_incompletas' => $mesas_incompletas,
            'mesasInc' => $mesasInc,
            'estadisticas' => $estadisticas,
            'ultima_ronda_tiene_resultados' => $ultima_ronda_tiene_resultados,
            'ultima_actualizacion_resultados' => null,
            'correcciones_cierre_at' => $correcciones_cierre_at,
            'actas_pendientes_count' => $actas_pendientes_count,
            'mesas_verificadas_count' => $mesas_verificadas_count,
            'mesas_digitadas_count' => $mesas_digitadas_count,
            'primera_mesa' => $primera_mesa,
            'es_modalidad_equipos' => $es_modalidad_equipos,
            'es_modalidad_parejas' => $es_modalidad_parejas,
            'es_modalidad_parejas_fijas' => $es_modalidad_parejas_fijas,
            'es_modalidad_equipos_o_parejas' => $es_modalidad_equipos_o_parejas,
            'torneo_bloqueado_inscripciones' => $torneo_bloqueado_inscripciones,
            'label_modalidad' => $label_modalidad,
            'podios_action' => $podios_action,
            'totalRondas' => $totalRondas,
            'isLocked' => $isLocked,
            'torneo_completado' => $torneo_completado,
            'puedeCerrar' => $puedeCerrar,
            'countdown_fin_timestamp' => $countdown_fin_timestamp,
            'mostrar_aviso_20min' => $mostrar_aviso_20min,
            'tiempo_ronda_minutos' => $tiempo_ronda_min,
            'page_title' => $page_title,
        ];
    }

    private static function labelModalidad(int $modalidad_num): string
    {
        if ($modalidad_num === 3) {
            return 'Equipos';
        }
        if ($modalidad_num === 4) {
            return 'Parejas fijas';
        }
        if ($modalidad_num === 2) {
            return 'Parejas';
        }
        return 'Individual';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function fetchRondasGeneradas(PDO $pdo, int $torneoId): array
    {
        $sql = 'SELECT 
                partida as num_ronda,
                COUNT(DISTINCT mesa) as total_mesas,
                COUNT(*) as total_jugadores,
                COUNT(CASE WHEN mesa = 0 THEN 1 END) as jugadores_bye,
                MAX(fecha_partida) as fecha_generacion
            FROM partiresul
            WHERE id_torneo = ?
            GROUP BY partida
            ORDER BY partida ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$torneoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function countMesasIncompletas(PDO $pdo, int $torneoId, int $ronda): int
    {
        $sql = 'SELECT COUNT(DISTINCT pr.mesa) as mesas_incompletas
            FROM partiresul pr
            WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa > 0
            AND (pr.registrado = 0 OR pr.registrado IS NULL)';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$torneoId, $ronda]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($result['mesas_incompletas'] ?? 0);
    }

    private static function countActasPendientesVerificacion(PDO $pdo, int $torneoId): int
    {
        try {
            $cols_pr = $pdo->query('SHOW COLUMNS FROM partiresul')->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('estatus', $cols_pr, true)) {
                return 0;
            }
            $has_origen = in_array('origen_dato', $cols_pr, true);
            $sql = '
                SELECT COUNT(DISTINCT CONCAT(partida,\'-\',mesa))
                FROM partiresul
                WHERE id_torneo = ? AND mesa > 0 AND estatus = \'pendiente_verificacion\''
                . ($has_origen ? " AND origen_dato = 'qr'" : '');
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$torneoId]);

            return (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * @return array{0:int,1:int}
     */
    private static function countMesasAuditoria(PDO $pdo, int $torneoId): array
    {
        $verificadas = 0;
        $digitadas = 0;
        try {
            $cols_pr = $pdo->query('SHOW COLUMNS FROM partiresul')->fetchAll(PDO::FETCH_COLUMN);
            $has_origen = in_array('origen_dato', $cols_pr, true);
            if ($has_origen) {
                $stmt = $pdo->prepare(
                    'SELECT COUNT(DISTINCT CONCAT(partida,\'-\',mesa))
                    FROM partiresul
                    WHERE id_torneo = ? AND mesa > 0 AND registrado = 1 AND origen_dato = \'qr\''
                );
                $stmt->execute([$torneoId]);
                $verificadas = (int) $stmt->fetchColumn();

                $stmt = $pdo->prepare(
                    'SELECT COUNT(DISTINCT CONCAT(partida,\'-\',mesa))
                    FROM partiresul
                    WHERE id_torneo = ? AND mesa > 0 AND registrado = 1 AND origen_dato = \'admin\''
                );
                $stmt->execute([$torneoId]);
                $digitadas = (int) $stmt->fetchColumn();
            }
        } catch (Throwable $e) {
            return [0, 0];
        }

        return [$verificadas, $digitadas];
    }

    private static function fetchPrimeraMesaUltimaRonda(PDO $pdo, int $torneoId, int $ultima_ronda): ?int
    {
        if ($ultima_ronda <= 0) {
            return null;
        }
        try {
            $stmt = $pdo->prepare(
                'SELECT MIN(CAST(mesa AS UNSIGNED)) as primera_mesa FROM partiresul WHERE id_torneo = ? AND partida = ? AND mesa > 0'
            );
            $stmt->execute([$torneoId, $ultima_ronda]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result === false || $result === null) {
                return null;
            }
            $pm = $result['primera_mesa'] ?? null;

            return $pm !== null && $pm !== '' ? (int) $pm : null;
        } catch (Throwable $e) {
            error_log('PanelTorneoViewData::fetchPrimeraMesaUltimaRonda: ' . $e->getMessage());

            return null;
        }
    }

    private static function ensureCorreccionesCierreColumn(): void
    {
        if (self::tournamentsCorreccionesCierreColumnExists()) {
            return;
        }
        try {
            $pdo = DB::pdo();
            $pdo->exec(
                "ALTER TABLE tournaments ADD COLUMN correcciones_cierre_at DATETIME NULL COMMENT 'Cierre de correcciones 20 min después de completar última mesa'"
            );
        } catch (Throwable $e) {
            // Instalaciones sin permiso ALTER: continuar sin columna
        }
    }

    private static function tournamentsCorreccionesCierreColumnExists(): bool
    {
        try {
            $pdo = DB::pdo();
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = \'tournaments\' AND COLUMN_NAME = \'correcciones_cierre_at\''
            );
            $stmt->execute();

            return ((int) $stmt->fetchColumn()) > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}
