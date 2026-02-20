<?php
/**
 * Finalizar torneo (Desktop): pone estatus = 0 y redirige al panel.
 */
declare(strict_types=1);
ob_start();
require_once __DIR__ . '/desktop_auth.php';
require_once __DIR__ . '/db_local.php';

$torneo_id = isset($_POST['torneo_id']) ? (int)$_POST['torneo_id'] : 0;
if ($torneo_id <= 0) {
    ob_end_clean();
    header('Location: panel_torneo.php');
    exit;
}
try {
    $pdo = DB_Local::pdo();
    $pdo->prepare("UPDATE tournaments SET estatus = 0, last_updated = ? WHERE id = ?")
        ->execute([date('Y-m-d H:i:s'), $torneo_id]);
} catch (Throwable $e) {
}
ob_end_clean();
header('Location: panel_torneo.php?torneo_id=' . $torneo_id . '&msg=finalizado');
exit;
