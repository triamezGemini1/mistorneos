<?php

declare(strict_types=1);

/**
 * Datos para el sitio público (sin autenticación): calendario, listados, fichas de torneo.
 */
final class PublicSiteService
{
    /**
     * Torneos visibles para el público (activos/publicados), más recientes primero.
     *
     * @return list<array<string, mixed>>
     */
    public static function listarTorneosPublicos(PDO $pdo, int $limite = 80): array
    {
        $lim = max(1, min(200, $limite));

        try {
            $sql = <<<SQL
                SELECT t.id, t.nombre, t.slug, t.tipo_torneo, t.estatus, t.fechator, t.lugar,
                       t.club_responsable, t.organizacion_id,
                       COALESCE(o.nombre, '') AS organizacion_nombre
                FROM tournaments t
                LEFT JOIN organizaciones o ON o.id = COALESCE(NULLIF(t.organizacion_id, 0), t.club_responsable)
                WHERE (t.estatus IS NULL OR t.estatus = 1)
                ORDER BY t.fechator DESC, t.id DESC
                LIMIT {$lim}
                SQL;
            $st = $pdo->query($sql);

            return $st !== false ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $e) {
            try {
                $sql2 = "SELECT id, nombre, slug, tipo_torneo, estatus, fechator, lugar, club_responsable, organizacion_id,
                        '' AS organizacion_nombre
                        FROM tournaments
                        WHERE estatus IS NULL OR estatus = 1
                        ORDER BY fechator DESC, id DESC
                        LIMIT {$lim}";
                $st = $pdo->query($sql2);

                return $st !== false ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
            } catch (Throwable $e2) {
                try {
                    $sql3 = "SELECT id, nombre, slug, tipo_torneo, estatus, fechator, lugar, club_responsable,
                            '' AS organizacion_nombre, 0 AS organizacion_id
                            FROM tournaments
                            ORDER BY id DESC
                            LIMIT {$lim}";
                    $st = $pdo->query($sql3);

                    return $st !== false ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
                } catch (Throwable $e3) {
                    error_log('PublicSiteService::listarTorneosPublicos: ' . $e3->getMessage());

                    return [];
                }
            }
        }
    }

    /**
     * @return ?array<string, mixed>
     */
    public static function obtenerTorneoPublico(PDO $pdo, int $torneoId): ?array
    {
        if ($torneoId <= 0) {
            return null;
        }

        try {
            $st = $pdo->prepare(
                'SELECT t.id, t.nombre, t.slug, t.tipo_torneo, t.estatus, t.fechator, t.lugar,
                        t.club_responsable, t.organizacion_id,
                        COALESCE(o.nombre, \'\') AS organizacion_nombre
                 FROM tournaments t
                 LEFT JOIN organizaciones o ON o.id = COALESCE(NULLIF(t.organizacion_id, 0), t.club_responsable)
                 WHERE t.id = ?
                 LIMIT 1'
            );
            $st->execute([$torneoId]);
            $r = $st->fetch(PDO::FETCH_ASSOC);

            return $r !== false ? $r : null;
        } catch (Throwable $e) {
            try {
                $st = $pdo->prepare(
                    'SELECT id, nombre, slug, tipo_torneo, estatus, fechator, lugar, club_responsable, organizacion_id
                     FROM tournaments WHERE id = ? LIMIT 1'
                );
                $st->execute([$torneoId]);
                $r = $st->fetch(PDO::FETCH_ASSOC);
                if ($r !== false) {
                    $r['organizacion_nombre'] = '';

                    return $r;
                }
            } catch (Throwable $e2) {
                // ignore
            }

            return null;
        }
    }

    public static function contarInscritosTorneo(PDO $pdo, int $torneoId): int
    {
        if ($torneoId <= 0) {
            return 0;
        }
        try {
            $st = $pdo->prepare('SELECT COUNT(*) FROM inscritos WHERE torneo_id = ?');
            $st->execute([$torneoId]);

            return (int) $st->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    public static function contarPartidasRegistradas(PDO $pdo, int $torneoId): int
    {
        if ($torneoId <= 0) {
            return 0;
        }
        try {
            $st = $pdo->prepare('SELECT COUNT(*) FROM partiresul WHERE id_torneo = ? AND registrado = 1');
            $st->execute([$torneoId]);

            return (int) $st->fetchColumn();
        } catch (Throwable $e) {
            try {
                $st = $pdo->prepare('SELECT COUNT(*) FROM partiresul WHERE id_torneo = ?');
                $st->execute([$torneoId]);

                return (int) $st->fetchColumn();
            } catch (Throwable $e2) {
                return 0;
            }
        }
    }
}
