<?php

declare(strict_types=1);

/**
 * Lecturas de clubes siempre acotadas por organización activa (scope).
 */
final class ClubService
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function listarPorOrganizacion(PDO $pdo, int $organizacionId, int $limite = 500): array
    {
        if ($organizacionId <= 0) {
            return [];
        }

        $sql = 'SELECT id, nombre, organizacion_id, entidad_id, estatus FROM clubes WHERE organizacion_id = ? ORDER BY nombre ASC LIMIT ' . (int) $limite;

        try {
            $st = $pdo->prepare($sql);
            $st->execute([$organizacionId]);

            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $sql2 = 'SELECT id, nombre, organizacion_id, estatus FROM clubes WHERE organizacion_id = ? ORDER BY nombre ASC LIMIT ' . (int) $limite;
            $st = $pdo->prepare($sql2);
            $st->execute([$organizacionId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$r) {
                $r['entidad_id'] = $r['entidad_id'] ?? null;
            }
            unset($r);

            return $rows;
        }
    }

    /**
     * @return ?array<string, mixed>
     */
    public static function obtenerEnOrganizacion(PDO $pdo, int $clubId, int $organizacionId): ?array
    {
        if ($clubId <= 0 || $organizacionId <= 0) {
            return null;
        }

        try {
            $st = $pdo->prepare(
                'SELECT id, nombre, organizacion_id, entidad_id, estatus FROM clubes WHERE id = ? AND organizacion_id = ? LIMIT 1'
            );
            $st->execute([$clubId, $organizacionId]);
            $r = $st->fetch(PDO::FETCH_ASSOC);

            return $r !== false ? $r : null;
        } catch (Throwable $e) {
            $st = $pdo->prepare(
                'SELECT id, nombre, organizacion_id, estatus FROM clubes WHERE id = ? AND organizacion_id = ? LIMIT 1'
            );
            $st->execute([$clubId, $organizacionId]);
            $r = $st->fetch(PDO::FETCH_ASSOC);

            return $r !== false ? $r : null;
        }
    }
}
