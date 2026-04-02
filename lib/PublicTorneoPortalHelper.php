<?php
declare(strict_types=1);

/**
 * Portal público info torneo (mesa + resumen + listados): sesión y datos de solo lectura.
 */
final class PublicTorneoPortalHelper
{
    public const SESSION_KEY = 'info_torneo_portal_v1';

    /** Torneo visible y en curso (no cerrado). */
    public static function getTorneoParaPortal(\PDO $pdo, int $torneoId): ?array
    {
        $st = $pdo->prepare('SELECT id, nombre, modalidad, rondas, estatus, locked, fechator, lugar FROM tournaments WHERE id = ?');
        $st->execute([$torneoId]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        if ((int) ($row['estatus'] ?? 0) !== 1) {
            return null;
        }
        if ((int) ($row['locked'] ?? 0) === 1) {
            return null;
        }
        return $row;
    }

    /** Torneo existe (para mensaje “finalizado”). */
    public static function getTorneoBasico(\PDO $pdo, int $torneoId): ?array
    {
        $st = $pdo->prepare('SELECT id, nombre, estatus, locked FROM tournaments WHERE id = ?');
        $st->execute([$torneoId]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function sessionGetUserId(int $torneoId): ?int
    {
        $bag = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($bag)) {
            return null;
        }
        if ((int) ($bag['torneo_id'] ?? 0) !== $torneoId) {
            return null;
        }
        $uid = (int) ($bag['id_usuario'] ?? 0);
        return $uid > 0 ? $uid : null;
    }

    public static function sessionSet(int $torneoId, int $idUsuario): void
    {
        $_SESSION[self::SESSION_KEY] = [
            'torneo_id' => $torneoId,
            'id_usuario' => $idUsuario,
            'ts' => time(),
        ];
    }

    public static function sessionClear(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function fetchListadoGeneral(\PDO $pdo, int $torneoId): array
    {
        $st = $pdo->prepare(
            'SELECT 
                i.id_usuario,
                i.ptosrnk,
                i.efectividad,
                i.ganados,
                i.perdidos,
                i.puntos,
                i.posicion,
                COALESCE(u.nombre, u.username) AS nombre_jugador,
                u.cedula,
                c.nombre AS club_nombre,
                i.codigo_equipo
            FROM inscritos i
            LEFT JOIN usuarios u ON i.id_usuario = u.id
            LEFT JOIN clubes c ON i.id_club = c.id
            WHERE i.torneo_id = ?
            AND (i.estatus IN (1, 2, \'1\', \'2\', \'confirmado\', \'solvente\'))
            ORDER BY i.ptosrnk DESC, i.efectividad DESC, i.ganados DESC, nombre_jugador ASC'
        );
        $st->execute([$torneoId]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @return array{resumen: array<string, mixed>, partidas: list<array<string, mixed>>, posicion: int, jugador: array<string, mixed>}
     */
    public static function fetchResumenParticipacion(\PDO $pdo, int $torneoId, int $idUsuario): array
    {
        require_once __DIR__ . '/InscritosPartiresulHelper.php';

        $st = $pdo->prepare(
            'SELECT u.id AS id_usuario, u.nombre, u.cedula, i.codigo_equipo
             FROM inscritos i
             INNER JOIN usuarios u ON u.id = i.id_usuario
             WHERE i.torneo_id = ? AND i.id_usuario = ?
             LIMIT 1'
        );
        $st->execute([$torneoId, $idUsuario]);
        $jugador = $st->fetch(\PDO::FETCH_ASSOC) ?: ['id_usuario' => $idUsuario, 'nombre' => '', 'cedula' => '', 'codigo_equipo' => ''];

        $stats = InscritosPartiresulHelper::obtenerEstadisticas($idUsuario, $torneoId);
        $puntos = (int) ($stats['puntos'] ?? 0);
        $efectividad = (int) ($stats['efectividad'] ?? 0);

        $st = $pdo->prepare(
            'SELECT pr.id_usuario,
                    COALESCE(SUM(pr.resultado1), 0) AS pts,
                    COALESCE(SUM(pr.efectividad), 0) AS ef
             FROM partiresul pr
             INNER JOIN inscritos i ON i.id_usuario = pr.id_usuario AND i.torneo_id = pr.id_torneo
             WHERE pr.id_torneo = ? AND pr.registrado = 1
             AND (i.estatus IS NULL OR i.estatus = 1 OR i.estatus = 2 OR i.estatus = \'1\' OR i.estatus = \'confirmado\' OR i.estatus = \'solvente\')
             GROUP BY pr.id_usuario'
        );
        $st->execute([$torneoId]);
        $todos = $st->fetchAll(\PDO::FETCH_ASSOC);
        $posicion = 1;
        foreach ($todos as $row) {
            $pt = (int) ($row['pts'] ?? 0);
            $ef = (int) ($row['ef'] ?? 0);
            if ($pt > $puntos || ($pt === $puntos && $ef > $efectividad)) {
                $posicion++;
            }
        }

        $st = $pdo->prepare(
            'SELECT partida, mesa, secuencia, resultado1, resultado2, efectividad, ff, registrado
             FROM partiresul
             WHERE id_torneo = ? AND id_usuario = ?
             ORDER BY partida ASC, CAST(mesa AS UNSIGNED) ASC'
        );
        $st->execute([$torneoId, $idUsuario]);
        $partidas_raw = $st->fetchAll(\PDO::FETCH_ASSOC);
        $partidas = [];
        foreach ($partidas_raw as $p) {
            $mesa = (int) $p['mesa'];
            $sec = (int) ($p['secuencia'] ?? 0);
            $r1 = (int) ($p['resultado1'] ?? 0);
            $r2 = (int) ($p['resultado2'] ?? 0);
            $compañero = '';
            $contrario1 = '';
            $contrario2 = '';
            $ganada = 0;
            if ($mesa > 0) {
                $stmt_mesa = $pdo->prepare(
                    'SELECT pr.id_usuario, pr.secuencia, COALESCE(u.nombre, u.username) AS nombre
                     FROM partiresul pr
                     INNER JOIN usuarios u ON u.id = pr.id_usuario
                     WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa = ?
                     ORDER BY pr.secuencia ASC'
                );
                $stmt_mesa->execute([$torneoId, $p['partida'], $p['mesa']]);
                $en_mesa = $stmt_mesa->fetchAll(\PDO::FETCH_ASSOC);
                $mi_equipo = in_array($sec, [1, 2], true) ? [1, 2] : [3, 4];
                $otro_equipo = in_array($sec, [1, 2], true) ? [3, 4] : [1, 2];
                foreach ($en_mesa as $row) {
                    $s = (int) $row['secuencia'];
                    if ((int) $row['id_usuario'] !== $idUsuario) {
                        if (in_array($s, $mi_equipo, true)) {
                            $compañero = $row['nombre'] ?? '—';
                        } else {
                            if ($contrario1 === '') {
                                $contrario1 = $row['nombre'] ?? '—';
                            } else {
                                $contrario2 = $row['nombre'] ?? '—';
                            }
                        }
                    }
                }
                $ganada = (in_array($sec, [1, 2], true) && $r1 > $r2) || (in_array($sec, [3, 4], true) && $r2 > $r1) ? 1 : 0;
            }
            $partidas[] = array_merge($p, [
                'compañero' => $compañero ?: '—',
                'contrario1' => $contrario1 ?: '—',
                'contrario2' => $contrario2 ?: '—',
                'ganada' => $ganada,
            ]);
        }

        $resumen = [
            'puntos' => $puntos,
            'efectividad' => $efectividad,
            'ganados' => (int) ($stats['ganados'] ?? 0),
            'perdidos' => (int) ($stats['perdidos'] ?? 0),
            'posicion' => $posicion,
        ];

        return [
            'jugador' => $jugador,
            'resumen' => $resumen,
            'partidas' => $partidas,
            'posicion' => $posicion,
        ];
    }
}
