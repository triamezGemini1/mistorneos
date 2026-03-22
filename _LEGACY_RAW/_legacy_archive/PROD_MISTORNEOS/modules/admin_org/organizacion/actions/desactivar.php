<?php
/**
 * Action: Desactivar organización (solo admin_general)
 * Centraliza la lógica que antes estaba en index.php
 */

if (!defined('APP_BOOTSTRAPPED')) {
    require_once __DIR__ . '/../../../config/bootstrap.php';
}
require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/db.php';

Auth::requireRole(['admin_general']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: index.php?page=organizaciones&error=' . urlencode('ID inválido'));
    exit;
}

try {
    $stmt = DB::pdo()->prepare("UPDATE organizaciones SET estatus = 0, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$id]);
    $entidad_id = isset($_GET['entidad_id']) ? (int)$_GET['entidad_id'] : 0;
    $return_to = $_GET['return_to'] ?? '';
    if ($return_to === 'organizaciones' && $entidad_id > 0) {
        header('Location: index.php?page=organizaciones&entidad_id=' . $entidad_id . '&success=' . urlencode('Organización desactivada'));
    } else {
        header('Location: index.php?page=organizaciones&success=' . urlencode('Organización desactivada'));
    }
    exit;
} catch (Exception $e) {
    header('Location: index.php?page=organizaciones&error=' . urlencode('Error al desactivar: ' . $e->getMessage()));
    exit;
}
