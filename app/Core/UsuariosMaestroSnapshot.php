<?php

declare(strict_types=1);

/**
 * Lecturas desde la tabla maestra de usuarios de la plataforma (`usuarios` / `users`).
 */
final class UsuariosMaestroSnapshot
{
    private const STAFF_ROLES = ['admin_general', 'admin_torneo', 'admin_club', 'operador'];

    /**
     * Top 5 cuentas con rol de gestión, ordenadas por actividad reciente en la maestra.
     *
     * @return list<array{nombre:string,username:string,rol:string,cedula:string,ref:string}>
     */
    public static function top5StaffRecientes(PDO $pdo): array
    {
        $tabla = self::tablaMaestra();
        $roles = self::STAFF_ROLES;
        $in = implode(',', array_fill(0, count($roles), '?'));

        $sqlWithLu = <<<SQL
            SELECT nombre, username, role, cedula,
                   COALESCE(last_updated, approved_at, requested_at) AS ref
            FROM `{$tabla}`
            WHERE role IN ({$in}) AND status = 0
            ORDER BY ref DESC, id DESC
            LIMIT 5
            SQL;

        try {
            $st = $pdo->prepare($sqlWithLu);
            $st->execute($roles);

            return self::mapear($st->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            error_log('UsuariosMaestroSnapshot (intento con last_updated): ' . $e->getMessage());
        }

        $sql = <<<SQL
            SELECT nombre, username, role, cedula,
                   COALESCE(approved_at, requested_at) AS ref
            FROM `{$tabla}`
            WHERE role IN ({$in}) AND status = 0
            ORDER BY ref DESC, id DESC
            LIMIT 5
            SQL;

        try {
            $st = $pdo->prepare($sql);
            $st->execute($roles);

            return self::mapear($st->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            error_log('UsuariosMaestroSnapshot top5: ' . $e->getMessage());

            return [];
        }
    }

    private static function tablaMaestra(): string
    {
        $t = strtolower(trim((string) (getenv('DB_AUTH_TABLE') ?: 'usuarios')));

        return in_array($t, ['usuarios', 'users'], true) ? $t : 'usuarios';
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array{nombre:string,username:string,rol:string,cedula:string,ref:string}>
     */
    private static function mapear(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $ref = $row['ref'] ?? null;
            $refStr = $ref !== null && $ref !== '' ? (string) $ref : '—';
            $out[] = [
                'nombre' => (string) ($row['nombre'] ?? ''),
                'username' => (string) ($row['username'] ?? ''),
                'rol' => (string) ($row['role'] ?? ''),
                'cedula' => (string) ($row['cedula'] ?? ''),
                'ref' => $refStr,
            ];
        }

        return $out;
    }
}
