<?php

declare(strict_types=1);

require_once __DIR__ . '/../Helpers/SlugHelper.php';

/**
 * Invitaciones, inscripciones y disparador de Ronda 1.
 */
final class TournamentEngineService
{
    public static function findTorneoBySlug(PDO $pdo, string $slug): ?array
    {
        $slug = trim($slug);
        if ($slug === '' || strlen($slug) > 150) {
            return null;
        }

        try {
            $st = $pdo->prepare(
                'SELECT id, nombre, slug, tipo_torneo, club_responsable, organizacion_id, entidad_id, estatus FROM tournaments WHERE slug = ? LIMIT 1'
            );
            $st->execute([$slug]);
            $row = $st->fetch(PDO::FETCH_ASSOC);

            return $row !== false ? $row : null;
        } catch (Throwable $e) {
            try {
                $st = $pdo->prepare(
                    'SELECT id, nombre, slug, tipo_torneo, club_responsable, estatus FROM tournaments WHERE slug = ? LIMIT 1'
                );
                $st->execute([$slug]);
                $row = $st->fetch(PDO::FETCH_ASSOC);

                return $row !== false ? $row : null;
            } catch (Throwable $e2) {
                error_log('TournamentEngineService::findTorneoBySlug: ' . $e->getMessage());

                return null;
            }
        }
    }

    /**
     * Garantiza slug único para un torneo.
     */
    public static function assignUniqueSlug(PDO $pdo, int $torneoId, string $nombreBase): string
    {
        $base = SlugHelper::slugify($nombreBase);
        $candidate = $base;
        $n = 0;
        do {
            $st = $pdo->prepare('SELECT id FROM tournaments WHERE slug = ? AND id <> ? LIMIT 1');
            $st->execute([$candidate, $torneoId]);
            if ($st->fetchColumn() === false) {
                $up = $pdo->prepare('UPDATE tournaments SET slug = ? WHERE id = ?');
                $up->execute([$candidate, $torneoId]);

                return $candidate;
            }
            $n++;
            $candidate = $base . '-' . $n;
        } while ($n < 500);

        $candidate = $base . '-' . bin2hex(random_bytes(3));
        $up = $pdo->prepare('UPDATE tournaments SET slug = ? WHERE id = ?');
        $up->execute([$candidate, $torneoId]);

        return $candidate;
    }

    public static function countRatificados(PDO $pdo, int $torneoId): int
    {
        try {
            $st = $pdo->prepare('SELECT COUNT(*) FROM inscritos WHERE torneo_id = ? AND ratificado = 1');
            $st->execute([$torneoId]);

            return (int) $st->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    public static function puedeGenerarRonda1(PDO $pdo, int $torneoId): bool
    {
        try {
            $st = $pdo->prepare('SELECT tipo_torneo FROM tournaments WHERE id = ? LIMIT 1');
            $st->execute([$torneoId]);
            $tipo = (string) $st->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }

        if ($tipo !== 'individual') {
            return false;
        }

        return self::countRatificados($pdo, $torneoId) >= 8;
    }

    /**
     * Inscribe usuario en torneo si no existe fila (estatus inicial pendiente).
     *
     * @return array{ok: bool, error?: string, inscrito_id?: int}
     */
    public static function inscribirUsuarioEnTorneo(PDO $pdo, int $torneoId, int $usuarioId): array
    {
        try {
            $st = $pdo->prepare('SELECT id FROM inscritos WHERE torneo_id = ? AND id_usuario = ? LIMIT 1');
            $st->execute([$torneoId, $usuarioId]);
            if ($st->fetchColumn() !== false) {
                return ['ok' => true, 'error' => 'ya_inscrito'];
            }

            try {
                $ins = $pdo->prepare(
                    'INSERT INTO inscritos (id_usuario, torneo_id, estatus, ratificado, presente_sitio) VALUES (?, ?, \'pendiente\', 0, 0)'
                );
                $ins->execute([$usuarioId, $torneoId]);
            } catch (Throwable $e1) {
                $ins = $pdo->prepare(
                    'INSERT INTO inscritos (id_usuario, torneo_id, estatus) VALUES (?, ?, \'pendiente\')'
                );
                $ins->execute([$usuarioId, $torneoId]);
            }

            return ['ok' => true, 'inscrito_id' => (int) $pdo->lastInsertId()];
        } catch (Throwable $e) {
            error_log('TournamentEngineService::inscribirUsuarioEnTorneo: ' . $e->getMessage());

            return ['ok' => false, 'error' => 'db'];
        }
    }

    /**
     * @param ?int $organizacionScope Si se indica, exige que el torneo pertenezca a esa organización (ámbito admin).
     * @return ?array<string, mixed>
     */
    public static function getTorneo(PDO $pdo, int $id, ?int $organizacionScope = null): ?array
    {
        $scope = ($organizacionScope !== null && $organizacionScope > 0) ? $organizacionScope : null;

        try {
            $sql = 'SELECT id, nombre, slug, tipo_torneo, club_responsable, organizacion_id, entidad_id, estatus FROM tournaments WHERE id = ?';
            $params = [$id];
            if ($scope !== null) {
                $sql .= ' AND COALESCE(NULLIF(organizacion_id, 0), NULLIF(club_responsable, 0)) = ?';
                $params[] = $scope;
            }
            $sql .= ' LIMIT 1';
            $st = $pdo->prepare($sql);
            $st->execute($params);
            $r = $st->fetch(PDO::FETCH_ASSOC);

            return $r !== false ? $r : null;
        } catch (Throwable $e) {
            try {
                $sql = 'SELECT id, nombre, slug, tipo_torneo, club_responsable, estatus FROM tournaments WHERE id = ?';
                $params = [$id];
                if ($scope !== null) {
                    $sql .= ' AND club_responsable = ?';
                    $params[] = $scope;
                }
                $sql .= ' LIMIT 1';
                $st = $pdo->prepare($sql);
                $st->execute($params);
                $r = $st->fetch(PDO::FETCH_ASSOC);

                return $r !== false ? $r : null;
            } catch (Throwable $e2) {
                try {
                    $st = $pdo->prepare(
                        'SELECT id, nombre, club_responsable, estatus FROM tournaments WHERE id = ? LIMIT 1'
                    );
                    $st->execute([$id]);
                    $r = $st->fetch(PDO::FETCH_ASSOC);
                    if ($r === false) {
                        return null;
                    }
                    $r['slug'] = $r['slug'] ?? null;
                    $r['tipo_torneo'] = 'individual';

                    return $r;
                } catch (Throwable $e3) {
                    return null;
                }
            }
        }
    }
}
