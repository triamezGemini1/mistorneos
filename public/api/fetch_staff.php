<?php
/**
 * Endpoint de sincronización: devuelve administradores/staff para la app desktop.
 * Solo accesible con API_KEY. Misma autenticación que fetch_jugadores.php.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$baseDir = dirname(__DIR__, 2);
if (!is_file($baseDir . '/config/bootstrap.php')) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Not Found', 'staff' => []]);
    exit;
}
require_once $baseDir . '/config/bootstrap.php';
require_once $baseDir . '/config/db.php';

$apiKey = trim((string)($_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? ''));
error_log('[fetch_staff] API key recibida (longitud ' . strlen($apiKey) . '): ' . (strlen($apiKey) > 0 ? substr($apiKey, 0, 4) . '...' : '(vacía)'));

$expectedKey = trim((string)(class_exists('Env') ? (Env::get('SYNC_API_KEY') ?: Env::get('API_KEY')) : ''));
$hardcodedKey = 'TorneoMaster2024*';
$valid = $apiKey !== '' && (
    ($expectedKey !== '' && hash_equals($expectedKey, $apiKey)) ||
    hash_equals($hardcodedKey, $apiKey)
);

if (!$valid) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'No autorizado.', 'staff' => []]);
    exit;
}

$roles = ['admin_general', 'admin_torneo', 'admin_club', 'operador'];

try {
    $pdo = DB::pdo();
    $columns = ['id', 'uuid', 'nombre', 'cedula', 'email', 'username', 'club_id', 'entidad', 'status', 'role'];
    $hasLastUpdated = false;
    $hasSyncStatus = false;
    $hasIsActive = false;
    try {
        if ($pdo->query("SHOW COLUMNS FROM usuarios LIKE 'last_updated'")->fetch()) {
            $columns[] = 'last_updated';
            $hasLastUpdated = true;
        }
        if ($pdo->query("SHOW COLUMNS FROM usuarios LIKE 'sync_status'")->fetch()) {
            $columns[] = 'sync_status';
            $hasSyncStatus = true;
        }
        if ($pdo->query("SHOW COLUMNS FROM usuarios LIKE 'is_active'")->fetch()) {
            $columns[] = 'is_active';
            $hasIsActive = true;
        }
    } catch (Throwable $e) {
    }
    $cols = implode(', ', $columns);
    $placeholders = implode(',', array_fill(0, count($roles), '?'));
    $sql = "SELECT {$cols} FROM usuarios WHERE role IN ({$placeholders}) ORDER BY role = 'admin_general' DESC, username";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($roles);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        if (isset($row['last_updated']) && $row['last_updated'] !== null) {
            $row['last_updated'] = (string)$row['last_updated'];
        }
        if (!$hasIsActive) {
            $row['is_active'] = 1;
        }
        if (empty($row['uuid'])) {
            $row['uuid'] = sprintf('%s-%s-staff', $row['id'] ?? 0, bin2hex(random_bytes(8)));
        }
    }
    unset($row);

    echo json_encode(['ok' => true, 'staff' => $rows, 'total' => count($rows)]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Error al obtener staff.', 'staff' => [], 'error' => $e->getMessage()]);
}
