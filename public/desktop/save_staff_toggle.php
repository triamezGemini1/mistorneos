<?php
/**
 * Guarda el cambio de is_active para un administrador. Marca sync_status = 0.
 * Solo el Master Admin (MASTER_ADMIN_EMAIL o MASTER_ADMIN_ID) puede ejecutar.
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

session_start();
if (empty($_SESSION['desktop_user'])) {
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}
if (file_exists(__DIR__ . '/config_sync.php')) {
    require __DIR__ . '/config_sync.php';
}
$current = $_SESSION['desktop_user'];
$masterEmail = defined('MASTER_ADMIN_EMAIL') ? trim((string)MASTER_ADMIN_EMAIL) : '';
$masterId = defined('MASTER_ADMIN_ID') ? (int)MASTER_ADMIN_ID : 0;
$currentId = (int)($current['id'] ?? 0);
$currentEmail = trim((string)($current['email'] ?? ''));
$isMaster = ($masterEmail !== '' && $currentEmail !== '' && strcasecmp($currentEmail, $masterEmail) === 0)
    || ($masterId > 0 && $currentId === $masterId);
if (!$isMaster) {
    echo json_encode(['ok' => false, 'error' => 'Solo el Master Admin puede modificar permisos']);
    exit;
}

$input = json_decode((string)file_get_contents('php://input'), true);
$id = isset($input['id']) ? (int)$input['id'] : 0;
$is_active = isset($input['is_active']) ? (int)$input['is_active'] : 0;
if ($id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'ID inválido']);
    exit;
}
if ($id === (int)($_SESSION['desktop_user']['id'] ?? 0)) {
    echo json_encode(['ok' => false, 'error' => 'No puedes desactivarte a ti mismo']);
    exit;
}

require_once __DIR__ . '/db_local.php';
try {
    $pdo = DB_Local::pdo();
    $pdo->prepare("UPDATE usuarios SET is_active = ?, sync_status = 0 WHERE id = ?")->execute([$is_active, $id]);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
