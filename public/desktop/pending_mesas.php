<?php
/**
 * Devuelve el número de mesas pendientes de registrar (partiresul.registrado = 0).
 * Usado por el Panel para actualizar el contador sin recargar la página.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/desktop_auth.php';
require_once __DIR__ . '/db_local.php';

$pendientes = 0;
try {
    $pdo = DB_Local::pdo();
    $exists = (bool) $pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='partiresul' LIMIT 1")->fetch();
    if ($exists) {
        // Solo mesas de juego (mesa > 0); mesa 0 = bye
        $stmt = $pdo->query("
            SELECT COUNT(*) FROM (
                SELECT id_torneo, partida, mesa FROM partiresul WHERE mesa > 0 AND (registrado = 0 OR registrado IS NULL) GROUP BY id_torneo, partida, mesa
            )
        ");
        $pendientes = (int) $stmt->fetchColumn();
    }
} catch (Throwable $e) {
}
echo json_encode(['pendientes' => $pendientes]);
