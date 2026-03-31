<?php
declare(strict_types=1);

require_once __DIR__ . '/SancionesHelper.php';

/**
 * Lectura de datos para la pantalla "Registrar resultados" (mesas, jugadores, estadísticas).
 * Sin efectos de sesión: el módulo aplica avisos en $_SESSION según el array flash devuelto.
 */
final class RegistrarResultadosLecturaService
{
    /**
     * @param array<int>|null $mesasOperador Números de mesa permitidos; null = sin filtro (no operador o ámbito total)
     * @return array{
     *   torneo: array<string, mixed>|false,
     *   ronda: int,
     *   mesaActual: int,
     *   jugadores: list<array<string, mixed>>,
     *   todasLasMesas: list<array{numero: int, registrado: int, tiene_resultados: bool}>,
     *   todasLasRondas: list<array<string, mixed>>,
     *   mesasCompletadas: int,
     *   mesasPendientes: int,
     *   totalMesas: int,
     *   mesaAnterior: int|null,
     *   mesaSiguiente: int|null,
     *   observacionesMesa: string,
     *   flash: array{warning: ?string, error: ?string}
     * }
     */
    public static function construirDatos(
        PDO $pdo,
        int $torneoId,
        int $ronda,
        int $mesaSolicitada,
        ?array $mesasOperador
    ): array {
        $stmt = $pdo->prepare('SELECT * FROM tournaments WHERE id = ?');
        $stmt->execute([$torneoId]);
        $torneo = $stmt->fetch(PDO::FETCH_ASSOC);

        $sqlRondas = 'SELECT DISTINCT partida FROM partiresul WHERE id_torneo = ? ORDER BY partida ASC';
        $stmt = $pdo->prepare($sqlRondas);
        $stmt->execute([$torneoId]);
        $todasLasRondas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sqlMesas = 'SELECT DISTINCT 
                pr.mesa as numero,
                MAX(pr.registrado) as registrado
            FROM partiresul pr
            WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa > 0
            GROUP BY pr.mesa
            ORDER BY CAST(pr.mesa AS UNSIGNED) ASC, pr.mesa ASC';
        $stmt = $pdo->prepare($sqlMesas);
        $stmt->execute([$torneoId, $ronda]);
        $todasLasMesasRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $mesasFiltradas = [];
        foreach ($todasLasMesasRaw as $m) {
            $numeroMesa = (int)$m['numero'];
            if ($numeroMesa > 0) {
                $mesasFiltradas[] = [
                    'numero' => $numeroMesa,
                    'registrado' => (int)($m['registrado'] ?? 0),
                    'tiene_resultados' => ($m['registrado'] ?? 0) > 0,
                ];
            }
        }
        usort($mesasFiltradas, static function ($a, $b) {
            return $a['numero'] <=> $b['numero'];
        });

        if ($mesasOperador !== null) {
            if (empty($mesasOperador)) {
                $mesasFiltradas = [];
            } else {
                $setOperador = array_flip($mesasOperador);
                $mesasFiltradas = array_values(array_filter($mesasFiltradas, static function ($m) use ($setOperador) {
                    return isset($setOperador[$m['numero']]);
                }));
            }
        }

        $todasLasMesas = $mesasFiltradas;
        $mesa = $mesaSolicitada;
        $mesasExistentes = array_column($todasLasMesas, 'numero');
        $maxMesa = $mesasExistentes !== [] ? max($mesasExistentes) : 0;

        $flashWarning = null;
        $flashError = null;

        if ($mesa > 0 && !in_array($mesa, $mesasExistentes, true)) {
            if ($mesasExistentes !== []) {
                $mesa = min($mesasExistentes);
                $flashWarning = "La mesa solicitada no está en su ámbito. Se ha redirigido a la mesa #{$mesa}.";
            } else {
                $flashError = $mesasOperador !== null
                    ? 'No tiene mesas asignadas para esta ronda. Contacte al administrador.'
                    : "No hay mesas asignadas para la ronda {$ronda}.";
                $mesa = 0;
            }
        }

        if ($mesa === 0 && $mesasExistentes !== []) {
            $mesa = min($mesasExistentes);
        }

        if ($maxMesa > 0 && $mesa > $maxMesa) {
            $mesa = $maxMesa;
            $flashWarning = "Se ha redirigido a la última mesa de su ámbito (mesa #{$maxMesa}).";
        }

        error_log('Mesas encontradas para torneo ' . $torneoId . ', ronda ' . $ronda . ': ' . implode(', ', array_column($todasLasMesas, 'numero')));

        $sqlJugadores = 'SELECT 
                pr.id,
                pr.*,
                u.nombre as nombre_completo,
                i.posicion,
                i.ganados,
                i.perdidos,
                i.efectividad,
                i.puntos as puntos_acumulados,
                i.sancion as sancion_acumulada,
                i.codigo_equipo,
                COALESCE(i.tarjeta, 0) AS tarjeta_inscritos
            FROM partiresul pr
            INNER JOIN usuarios u ON pr.id_usuario = u.id
            LEFT JOIN inscritos i ON i.id_usuario = u.id AND i.torneo_id = pr.id_torneo
            WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa = ?
            ORDER BY pr.secuencia ASC';

        $stmt = $pdo->prepare($sqlJugadores);
        $stmt->execute([$torneoId, $ronda, $mesa]);
        $jugadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($jugadores !== []) {
            usort($jugadores, static function ($a, $b) {
                return ((int)($a['secuencia'] ?? 0)) <=> ((int)($b['secuencia'] ?? 0));
            });
        }

        $tarjetaPreviaPorUsuario = [];
        if ($jugadores !== []) {
            $idsMesa = array_map(static function ($j) {
                return (int)$j['id_usuario'];
            }, $jugadores);
            $tarjetaPreviaPorUsuario = SancionesHelper::getTarjetaPreviaDesdePartidasAnteriores(
                $pdo,
                $torneoId,
                $ronda,
                array_values($idsMesa)
            );
        }

        foreach ($jugadores as &$jugador) {
            $idU = (int)$jugador['id_usuario'];
            $jugador['inscrito'] = [
                'posicion' => (int)($jugador['posicion'] ?? 0),
                'ganados' => (int)($jugador['ganados'] ?? 0),
                'perdidos' => (int)($jugador['perdidos'] ?? 0),
                'efectividad' => (int)($jugador['efectividad'] ?? 0),
                'puntos' => (int)($jugador['puntos'] ?? 0),
                'tarjeta' => (int)($jugador['tarjeta_inscritos'] ?? 0),
                'tarjeta_previa' => (int)($tarjetaPreviaPorUsuario[$idU] ?? 0),
            ];
        }
        unset($jugador);

        $observacionesMesa = '';
        if ($jugadores !== [] && isset($jugadores[0]['observaciones'])) {
            $observacionesMesa = (string)($jugadores[0]['observaciones'] ?? '');
        }

        $mesasCompletadas = 0;
        foreach ($todasLasMesas as $m) {
            if ($m['tiene_resultados']) {
                $mesasCompletadas++;
            }
        }
        $totalMesas = count($todasLasMesas);
        $mesasPendientes = $totalMesas - $mesasCompletadas;

        $mesaAnterior = null;
        $mesaSiguiente = null;
        foreach ($todasLasMesas as $index => $m) {
            if ($m['numero'] === $mesa) {
                if ($index > 0) {
                    $mesaAnterior = $todasLasMesas[$index - 1]['numero'];
                }
                if ($index < count($todasLasMesas) - 1) {
                    $mesaSiguiente = $todasLasMesas[$index + 1]['numero'];
                }
                break;
            }
        }

        return [
            'torneo' => $torneo,
            'ronda' => $ronda,
            'mesaActual' => $mesa,
            'jugadores' => $jugadores,
            'todasLasMesas' => $todasLasMesas,
            'todasLasRondas' => $todasLasRondas,
            'mesasCompletadas' => $mesasCompletadas,
            'mesasPendientes' => $mesasPendientes,
            'totalMesas' => $totalMesas,
            'mesaAnterior' => $mesaAnterior,
            'mesaSiguiente' => $mesaSiguiente,
            'observacionesMesa' => $observacionesMesa,
            'flash' => [
                'warning' => $flashWarning,
                'error' => $flashError,
            ],
        ];
    }
}
