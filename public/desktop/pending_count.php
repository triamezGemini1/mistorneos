<?php
/**
 * Devuelve el número de registros pendientes de sincronizar (sync_status = 0).
 * Uso: el indicador de estado y el script de sincronización en segundo plano.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/db_local.php';

try {
    $pdo = DB_Local::pdo();
    $stmt = $pdo->query("SELECT COUNT(*) AS pending FROM usuarios WHERE sync_status = 0");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $pending = (int)($row['pending'] ?? 0);
    echo json_encode(['pending' => $pending]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['pending' => 0, 'error' => $e->getMessage()]);
}
