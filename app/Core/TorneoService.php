<?php

declare(strict_types=1);

require_once __DIR__ . '/TorneoHierarchyException.php';

/**
 * Creación y lectura de torneos con herencia de entidad_id / organizacion_id desde el admin.
 * Las lecturas para operadores usan siempre WHERE organizacion_id = :scope.
 */
final class TorneoService
{
    /**
     * Crea un torneo heredando entidad y organización del administrador.
     * admin_general debe pasar organizacion_id explícito en $datos.
     *
     * @param array{nombre:string,fechator?:string,lugar?:string,organizacion_id?:int} $datos
     */
    public static function crearDesdeAdmin(PDO $pdo, array $adminSession, array $datos): int
    {
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        if ($nombre === '') {
            throw new TorneoHierarchyException('El nombre del torneo es obligatorio.');
        }

        $role = (string) ($adminSession['role'] ?? '');
        $orgSesion = (int) ($adminSession['organizacion_id'] ?? 0);

        $organizacionId = $orgSesion;
        if ($role === 'admin_general') {
            $organizacionId = (int) ($datos['organizacion_id'] ?? 0);
            if ($organizacionId <= 0) {
                throw new TorneoHierarchyException(
                    'Defina organizacion_id al crear un torneo como administrador general.'
                );
            }
        }

        if ($organizacionId <= 0) {
            throw new TorneoHierarchyException(
                'No hay organización en sesión; no se puede crear un torneo huérfano.'
            );
        }

        $entidadId = self::resolverEntidadIdDeOrganizacion($pdo, $organizacionId);
        if ($entidadId <= 0) {
            throw new TorneoHierarchyException(
                'La organización no tiene entidad_id válido; corrija datos o ejecute la migración de jerarquía.'
            );
        }

        $fechator = trim((string) ($datos['fechator'] ?? ''));
        if ($fechator === '') {
            $fechator = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        }

        $lugar = trim((string) ($datos['lugar'] ?? ''));

        try {
            $ins = $pdo->prepare(
                'INSERT INTO tournaments (nombre, fechator, lugar, club_responsable, organizacion_id, entidad_id, estatus, tipo_torneo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $ins->execute([
                $nombre,
                $fechator,
                $lugar !== '' ? $lugar : null,
                $organizacionId,
                $organizacionId,
                $entidadId,
                1,
                'individual',
            ]);

            return (int) $pdo->lastInsertId();
        } catch (Throwable $e) {
            try {
                $ins = $pdo->prepare(
                    'INSERT INTO tournaments (nombre, fechator, lugar, club_responsable, organizacion_id, entidad_id, estatus) VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $ins->execute([
                    $nombre,
                    $fechator,
                    $lugar !== '' ? $lugar : null,
                    $organizacionId,
                    $organizacionId,
                    $entidadId,
                    1,
                ]);

                return (int) $pdo->lastInsertId();
            } catch (Throwable $e2) {
                try {
                    $ins = $pdo->prepare(
                        'INSERT INTO tournaments (nombre, fechator, lugar, club_responsable, estatus) VALUES (?, ?, ?, ?, ?)'
                    );
                    $ins->execute([$nombre, $fechator, $lugar !== '' ? $lugar : null, $organizacionId, 1]);
                    $tid = (int) $pdo->lastInsertId();
                    try {
                        $up = $pdo->prepare('UPDATE tournaments SET organizacion_id = ?, entidad_id = ? WHERE id = ?');
                        $up->execute([$organizacionId, $entidadId, $tid]);
                    } catch (Throwable $e3) {
                        error_log('TorneoService::crearDesdeAdmin (UPDATE jerárquico): ' . $e3->getMessage());
                    }

                    return $tid;
                } catch (Throwable $e3) {
                    try {
                        $uuid = uniqid('mn_', true);
                        $ins = $pdo->prepare(
                            'INSERT INTO tournaments (nombre, clase, modalidad, tiempo, puntos, rondas, costo, estatus, entidad, uuid, fechator, club_responsable) VALUES (?, 0, 0, 35, 200, 9, 0, ?, ?, ?, ?, ?)'
                        );
                        $ins->execute([$nombre, 1, $entidadId, $uuid, $fechator, $organizacionId]);
                        $tid = (int) $pdo->lastInsertId();
                        try {
                            $up = $pdo->prepare('UPDATE tournaments SET organizacion_id = ?, entidad_id = ? WHERE id = ?');
                            $up->execute([$organizacionId, $entidadId, $tid]);
                        } catch (Throwable $e4) {
                            // columnas opcionales
                        }

                        return $tid;
                    } catch (Throwable $e4) {
                        error_log('TorneoService::crearDesdeAdmin: ' . $e->getMessage());

                        throw new TorneoHierarchyException('No se pudo insertar el torneo en la base de datos.');
                    }
                }
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listarPorOrganizacion(PDO $pdo, int $organizacionId, int $limite = 200): array
    {
        if ($organizacionId <= 0) {
            return [];
        }

        $lim = (int) $limite;
        $sql = <<<SQL
            SELECT id, nombre, slug, tipo_torneo, estatus, fechator, club_responsable, organizacion_id, entidad_id
            FROM tournaments
            WHERE organizacion_id = ?
            ORDER BY fechator DESC, id DESC
            LIMIT {$lim}
            SQL;

        try {
            $st = $pdo->prepare($sql);
            $st->execute([$organizacionId]);

            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $sql2 = <<<SQL
                SELECT id, nombre, estatus, fechator, club_responsable
                FROM tournaments
                WHERE club_responsable = ?
                ORDER BY fechator DESC, id DESC
                LIMIT {$lim}
                SQL;
            $st = $pdo->prepare($sql2);
            $st->execute([$organizacionId]);

            return $st->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    /**
     * Solo para admin_general: listado global (usar con moderación).
     *
     * @return list<array<string, mixed>>
     */
    public static function listarRecientesGlobal(PDO $pdo, int $limite = 100): array
    {
        $lim = (int) $limite;
        $sql = "SELECT id, nombre, slug, tipo_torneo, estatus, fechator, organizacion_id, entidad_id, club_responsable FROM tournaments ORDER BY id DESC LIMIT {$lim}";

        try {
            $st = $pdo->query($sql);

            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $sql2 = "SELECT id, nombre, estatus, fechator, club_responsable FROM tournaments ORDER BY id DESC LIMIT {$lim}";
            $st = $pdo->query($sql2);

            return $st->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    /**
     * @return ?array<string, mixed>
     */
    public static function obtenerEnOrganizacion(PDO $pdo, int $torneoId, int $organizacionId): ?array
    {
        if ($torneoId <= 0 || $organizacionId <= 0) {
            return null;
        }

        try {
            $st = $pdo->prepare(
                'SELECT id, nombre, slug, tipo_torneo, estatus, fechator, club_responsable, organizacion_id, entidad_id FROM tournaments WHERE id = ? AND organizacion_id = ? LIMIT 1'
            );
            $st->execute([$torneoId, $organizacionId]);
            $r = $st->fetch(PDO::FETCH_ASSOC);

            return $r !== false ? $r : null;
        } catch (Throwable $e) {
            $st = $pdo->prepare(
                'SELECT id, nombre, slug, tipo_torneo, estatus, fechator, club_responsable FROM tournaments WHERE id = ? AND club_responsable = ? LIMIT 1'
            );
            $st->execute([$torneoId, $organizacionId]);
            $r = $st->fetch(PDO::FETCH_ASSOC);

            return $r !== false ? $r : null;
        }
    }

    private static function resolverEntidadIdDeOrganizacion(PDO $pdo, int $organizacionId): int
    {
        try {
            $st = $pdo->prepare('SELECT entidad_id FROM organizaciones WHERE id = ? LIMIT 1');
            $st->execute([$organizacionId]);
            $v = $st->fetchColumn();

            return $v !== false ? (int) $v : 0;
        } catch (Throwable $e) {
            try {
                $st = $pdo->prepare('SELECT entidad FROM organizaciones WHERE id = ? LIMIT 1');
                $st->execute([$organizacionId]);
                $v = $st->fetchColumn();

                return $v !== false ? (int) $v : 0;
            } catch (Throwable $e2) {
                return 0;
            }
        }
    }
}
