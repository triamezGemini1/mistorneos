<?php

declare(strict_types=1);

/**
 * Multitenancy por organización: filtra torneos y clubes por id de organización.
 */
final class OrganizacionService
{
    /**
     * Asegura un workspace (fila en organizaciones) para admins que operan por tenant.
     * admin_general: sin tenant único (null).
     *
     * @return int|null id organización
     */
    public static function ensureWorkspaceForAdmin(
        PDO $pdo,
        int $userId,
        string $role,
        string $nombreUsuario,
        string $emailUsuario
    ): ?int {
        if ($role === 'admin_general') {
            return null;
        }

        try {
            $st = $pdo->prepare('SELECT id FROM organizaciones WHERE admin_user_id = ? ORDER BY id ASC LIMIT 1');
            $st->execute([$userId]);
            $id = $st->fetchColumn();
            if ($id !== false) {
                return (int) $id;
            }

            $nom = 'Workspace ' . mb_substr($nombreUsuario !== '' ? $nombreUsuario : $emailUsuario, 0, 48);
            $emailIns = mb_substr($emailUsuario, 0, 100);
            $entidadDefault = (int) (getenv('DEFAULT_ENTIDAD_ID') ?: 1);

            try {
                $ins = $pdo->prepare(
                    'INSERT INTO organizaciones (nombre, admin_user_id, entidad_id, estatus, email) VALUES (?, ?, ?, 1, ?)'
                );
                $ins->execute([$nom, $userId, $entidadDefault, $emailIns]);
            } catch (Throwable $e) {
                $ins = $pdo->prepare(
                    'INSERT INTO organizaciones (nombre, admin_user_id, entidad, estatus, email) VALUES (?, ?, ?, 1, ?)'
                );
                $ins->execute([$nom, $userId, $entidadDefault, $emailIns]);
            }

            $newId = (int) $pdo->lastInsertId();

            self::trySetUsuarioWorkspaceColumn($pdo, $userId, $newId);

            return $newId;
        } catch (Throwable $e) {
            error_log('OrganizacionService::ensureWorkspaceForAdmin: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Torneo pertenece al tenant del admin (club_responsable = id organización).
     */
    public static function adminPuedeGestionarTorneo(array $adminSession, array $torneoRow): bool
    {
        $role = (string) ($adminSession['role'] ?? '');
        if ($role === 'admin_general') {
            return true;
        }

        $orgId = $adminSession['organizacion_id'] ?? null;
        if ($orgId === null || (int) $orgId <= 0) {
            return false;
        }

        $orgTorneo = (int) ($torneoRow['organizacion_id'] ?? 0);
        if ($orgTorneo <= 0) {
            $orgTorneo = (int) ($torneoRow['club_responsable'] ?? 0);
        }

        return $orgTorneo === (int) $orgId;
    }

    private static function trySetUsuarioWorkspaceColumn(PDO $pdo, int $userId, int $orgId): void
    {
        try {
            $tabla = getenv('DB_AUTH_TABLE') ?: 'usuarios';
            $tabla = in_array(strtolower(trim((string) $tabla)), ['usuarios', 'users'], true) ? strtolower(trim((string) $tabla)) : 'usuarios';
            $st = $pdo->prepare("UPDATE `{$tabla}` SET organizacion_workspace_id = ? WHERE id = ?");
            $st->execute([$orgId, $userId]);
        } catch (Throwable $e) {
            // Columna opcional
        }
    }
}
