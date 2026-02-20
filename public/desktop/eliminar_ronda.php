<?php
/**
 * Eliminar última ronda (Desktop). Borra registros de partiresul para la ronda indicada.
 */
declare(strict_types=1);
ob_start();
require_once __DIR__ . '/desktop_auth.php';
require_once __DIR__ . '/db_local.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    header('Location: panel_torneo.php');
    exit;
}
$torneo_id = isset($_POST['torneo_id']) ? (int)$_POST['torneo_id'] : 0;
$ronda = isset($_POST['ronda']) ? (int)$_POST['ronda'] : 0;
if ($torneo_id <= 0 || $ronda <= 0) {
    ob_end_clean();
    header('Location: panel_torneo.php?torneo_id=' . $torneo_id . '&error=parametros');
    exit;
}
try {
    $pdo = DB_Local::pdo();
    $pdo->prepare("DELETE FROM partiresul WHERE id_torneo = ? AND partida = ?")->execute([$torneo_id, $ronda]);
} catch (Throwable $e) {
    ob_end_clean();
    header('Location: panel_torneo.php?torneo_id=' . $torneo_id . '&error=eliminar');
    exit;
}
ob_end_clean();
header('Location: panel_torneo.php?torneo_id=' . $torneo_id . '&msg=ronda_eliminada');
exit;
