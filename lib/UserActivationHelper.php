<?php

declare(strict_types=1);

/**
 * Activa usuarios para que puedan acceder al sistema (login, perfil, notificaciones).
 * Se usa para participantes de torneos: al inscribirse o por acción masiva.
 *
 * Reglas en la app: status = 0 → activo, status = 1 → inactivo (login rechazado).
 * Si existe columna is_active, 1 = puede entrar (Master Admin puede desactivar).
 */
class UserActivationHelper
{
    /** Activar un usuario por ID (status=0, is_active=1 si existe). */
    public static function activateUser(PDO $pdo, int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        return self::activateUsers($pdo, [$userId]) > 0;
    }

    /**
     * Activar varios usuarios por ID.
     * @return int Número de filas actualizadas
     */
    public static function activateUsers(PDO $pdo, array $userIds): int
    {
        $userIds = array_filter(array_map('intval', $userIds), fn($id) => $id > 0);
        if (empty($userIds)) {
            return 0;
        }
        $userIds = array_unique($userIds);
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));

        // status = 0 → activo (según schema y lib/security.php)
        $sql = "UPDATE usuarios SET status = 0 WHERE id IN ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($userIds));
        $updated = $stmt->rowCount();

        // is_active: si existe la columna, poner 1 para que puedan entrar (security.php lo comprueba)
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'is_active'");
            if ($stmt && $stmt->rowCount() > 0) {
                $sql2 = "UPDATE usuarios SET is_active = 1 WHERE id IN ($placeholders)";
                $stmt2 = $pdo->prepare($sql2);
                $stmt2->execute(array_values($userIds));
            }
        } catch (Throwable $e) {
            // Columna puede no existir
        }

        return $updated;
    }

    /**
     * Activar todos los usuarios que participan en un torneo (estén en inscritos, sin retirados).
     * Útil para "activar participantes" desde gestión del torneo.
     * @return int Número de usuarios activados
     */
    public static function activateTournamentParticipants(PDO $pdo, int $torneoId): int
    {
        if ($torneoId <= 0) {
            return 0;
        }
        $stmt = $pdo->prepare("
            SELECT DISTINCT i.id_usuario
            FROM inscritos i
            WHERE i.torneo_id = ? AND (i.estatus IS NULL OR (i.estatus != 'retirado' AND i.estatus != 4))
        ");
        $stmt->execute([$torneoId]);
        $ids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id_usuario');
        return self::activateUsers($pdo, $ids);
    }
}
