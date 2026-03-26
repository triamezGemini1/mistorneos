<?php

declare(strict_types=1);

/**
 * GestionTorneosViewsData - Datos para vistas de cuadricula y hojas de anotacion.
 * Usado por torneo_gestion y tournament_admin (wrappers).
 */
class GestionTorneosViewsData
{
    public static function obtenerCuadricula(int $torneo_id, int $ronda): array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("SELECT * FROM tournaments WHERE id = ?");
        $stmt->execute([$torneo_id]);
        $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
        $sql = "SELECT pr.id_usuario, pr.mesa, pr.secuencia, u.nombre as nombre_completo, u.username
                FROM partiresul pr INNER JOIN usuarios u ON pr.id_usuario = u.id
                WHERE pr.id_torneo = ? AND pr.partida = ? ORDER BY pr.id_usuario ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$torneo_id, $ronda]);
        $asignaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return [
            'titulo' => 'Cuadricula - Ronda ' . $ronda,
            'torneo' => $torneo,
            'numRonda' => $ronda,
            'asignaciones' => $asignaciones,
            'totalAsignaciones' => count($asignaciones),
        ];
    }

    public static function obtenerHojasAnotacion(int $torneo_id, int $ronda): array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("SELECT * FROM tournaments WHERE id = ?");
        $stmt->execute([$torneo_id]);
        $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
        $es_torneo_equipos = (int)($torneo['modalidad'] ?? 0) === 3;
        $stmt = $pdo->prepare("SELECT id_usuario, codigo_equipo, posicion, ganados, perdidos, efectividad, puntos, sancion, tarjeta FROM inscritos WHERE torneo_id = ? ORDER BY posicion ASC");
        $stmt->execute([$torneo_id]);
        $inscritos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $inscritosMap = [];
        foreach ($inscritos as $i) {
            $inscritosMap[$i['id_usuario']] = $i;
        }
        $equiposMap = [];
        $estadisticasEquipos = [];
        if ($es_torneo_equipos) {
            $stmt = $pdo->prepare("SELECT codigo_equipo, nombre_equipo, id_club FROM equipos WHERE id_torneo = ? AND estatus = 0");
            $stmt->execute([$torneo_id]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $e) {
                $equiposMap[$e['codigo_equipo']] = $e;
            }
            $stmt = $pdo->prepare("SELECT codigo_equipo, posicion, puntos, ganados, perdidos, efectividad FROM equipos WHERE id_torneo = ? AND estatus = 0 AND codigo_equipo IS NOT NULL AND codigo_equipo != '' ORDER BY posicion ASC");
            $stmt->execute([$torneo_id]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
                $estadisticasEquipos[$s['codigo_equipo']] = [
                    'posicion' => (int)$s['posicion'], 'clasiequi' => (int)$s['posicion'],
                    'puntos' => (int)$s['puntos'], 'ganados' => (int)$s['ganados'], 'perdidos' => (int)$s['perdidos'], 'efectividad' => (int)$s['efectividad'], 'total_jugadores' => 4,
                ];
            }
        }
        $stmt = $pdo->prepare("SELECT pr.*, u.nombre as nombre_completo, i.codigo_equipo, c.nombre as nombre_club FROM partiresul pr INNER JOIN usuarios u ON pr.id_usuario = u.id LEFT JOIN inscritos i ON i.id_usuario = u.id AND i.torneo_id = pr.id_torneo LEFT JOIN clubes c ON i.id_club = c.id WHERE pr.id_torneo = ? AND pr.partida = ? ORDER BY pr.mesa ASC, pr.secuencia ASC");
        $stmt->execute([$torneo_id, $ronda]);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $mesas = [];
        foreach ($resultados as $row) {
            $numMesa = (int)$row['mesa'];
            if ($numMesa <= 0) continue;
            if (!isset($mesas[$numMesa])) {
                $mesas[$numMesa] = ['numero' => $numMesa, 'jugadores' => []];
            }
            $inscritoData = $inscritosMap[$row['id_usuario']] ?? ['posicion' => 0, 'ganados' => 0, 'perdidos' => 0, 'efectividad' => 0, 'puntos' => 0, 'sancion' => 0, 'tarjeta' => 0, 'codigo_equipo' => null];
            $row['tarjeta'] = (int)($inscritoData['tarjeta'] ?? 0);
            $row['inscrito'] = ['posicion' => (int)$inscritoData['posicion'], 'ganados' => (int)$inscritoData['ganados'], 'perdidos' => (int)$inscritoData['perdidos'], 'efectividad' => (int)$inscritoData['efectividad'], 'puntos' => (int)$inscritoData['puntos'], 'sancion' => (int)$inscritoData['sancion'], 'tarjeta' => (int)$inscritoData['tarjeta']];
            $codigoEquipo = $row['codigo_equipo'] ?? $inscritoData['codigo_equipo'] ?? null;
            if ($es_torneo_equipos && $codigoEquipo && isset($equiposMap[$codigoEquipo])) {
                $row['nombre_equipo'] = $equiposMap[$codigoEquipo]['nombre_equipo'];
                $row['codigo_equipo_display'] = $equiposMap[$codigoEquipo]['codigo_equipo'];
            }
            if ($es_torneo_equipos && $codigoEquipo && isset($estadisticasEquipos[$codigoEquipo])) {
                $row['estadisticas_equipo'] = $estadisticasEquipos[$codigoEquipo];
            }
            $mesas[$numMesa]['jugadores'][] = $row;
        }
        return [
            'torneo' => $torneo,
            'ronda' => $ronda,
            'mesas' => array_values($mesas),
            'es_torneo_equipos' => $es_torneo_equipos,
        ];
    }
}
