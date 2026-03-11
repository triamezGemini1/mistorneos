<?php
/**
 * Action: Reactivar organización (solo admin_general).
 * Si la organización está inactiva (estatus = 0), redirige al formulario de activación
 * para asignar usuario administrador y contraseña. Si ya está activa, solo actualiza estatus.
 */

if (!defined('APP_BOOTSTRAPPED')) {
    require_once __DIR__ . '/../../../config/bootstrap.php';
}
require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/db.php';

Auth::requireRole(['admin_general']);

$base = (defined('URL_BASE') && URL_BASE !== '') ? rtrim(URL_BASE, '/') . '/' : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: ' . $base . 'index.php?page=organizaciones&error=' . urlencode('ID inválido'));
    exit;
}

$pdo = DB::pdo();
$stmt = $pdo->prepare("SELECT id, estatus FROM organizaciones WHERE id = ?");
$stmt->execute([$id]);
$org = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$org) {
    header('Location: ' . $base . 'index.php?page=organizaciones&error=' . urlencode('Organización no encontrada'));
    exit;
}

$entidad_id = isset($_GET['entidad_id']) ? (int)$_GET['entidad_id'] : 0;
$return_extra = '';
if (($_GET['return_to'] ?? '') === 'organizaciones' && $entidad_id > 0) {
    $return_extra = '&return_to=organizaciones&entidad_id=' . $entidad_id;
}

// Si está inactiva, ir al formulario para asignar usuario y contraseña
if ((int)$org['estatus'] === 0) {
    header('Location: ' . $base . 'index.php?page=mi_organizacion&id=' . $id . '&action=activar' . $return_extra);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE organizaciones SET estatus = 1, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$id]);
    if ($entidad_id > 0) {
        header('Location: ' . $base . 'index.php?page=organizaciones&entidad_id=' . $entidad_id . '&success=' . urlencode('Organización reactivada'));
    } else {
        header('Location: ' . $base . 'index.php?page=organizaciones&success=' . urlencode('Organización reactivada'));
    }
    exit;
} catch (Exception $e) {
    header('Location: ' . $base . 'index.php?page=organizaciones&error=' . urlencode('Error al reactivar: ' . $e->getMessage()));
    exit;
}
