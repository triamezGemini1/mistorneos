<?php
declare(strict_types=1);

/**
 * Ámbito de mesas para rol operador (tabla operador_mesa_asignacion).
 */
final class OperadorMesaAmbitoService
{
    /**
     * Números de mesa asignados al operador en la ronda, o null si no aplica restricción.
     *
     * @return array<int>|null null = sin filtro (no operador o sin tabla / error)
     */
    public static function mesasPermitidas(PDO $pdo, int $torneoId, int $ronda, int $userId, string $userRole): ?array
    {
        if ($userRole !== 'operador' || $userId <= 0) {
            return null;
        }
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'operador_mesa_asignacion'");
            if ($stmt === false || $stmt->rowCount() === 0) {
                return null;
            }
            $stmt = $pdo->prepare(
                'SELECT mesa_numero FROM operador_mesa_asignacion WHERE torneo_id = ? AND ronda = ? AND user_id_operador = ? ORDER BY mesa_numero ASC'
            );
            $stmt->execute([$torneoId, $ronda, $userId]);
            $nums = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'mesa_numero');

            return array_map('intval', $nums);
        } catch (Exception $e) {
            return null;
        }
    }
}
