<?php
/**
 * Generar Ronda (Desktop) — Pasos 3 y 8 del ciclo.
 * Invoca core/logica_torneo.php (generarRonda), que a su vez usa:
 * - core/MesaAsignacionService.php (individual) o MesaAsignacionEquiposService.php (equipos).
 * Las especificaciones (individual/parejas/equipos) se leen de la tabla tournaments en SQLite local (modalidad).
 * POST: torneo_id. Redirige al panel con status=success o error.
 */
declare(strict_types=1);
ob_start();
require_once __DIR__ . '/desktop_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    header('Location: torneos.php');
    exit;
}

$torneo_id = (int)($_POST['torneo_id'] ?? 0);
if ($torneo_id <= 0) {
    ob_end_clean();
    header('Location: torneos.php?error=' . urlencode('Torneo no válido'));
    exit;
}

require_once __DIR__ . '/../../desktop/core/db_bridge.php';
require_once __DIR__ . '/../../desktop/core/logica_torneo.php';

$user_id = (int)($_SESSION['desktop_user_id'] ?? 1);
$is_admin_general = true;
$opciones = [
    'estrategia_ronda2'      => trim((string)($_POST['estrategia_ronda2'] ?? 'separar')),
    'estrategia_asignacion'  => trim((string)($_POST['estrategia_asignacion'] ?? 'secuencial')),
];

$resultado = generarRonda($torneo_id, $user_id, $is_admin_general, $opciones);

ob_end_clean();
if ($resultado['success']) {
    header('Location: panel_torneo.php?torneo_id=' . $torneo_id . '&msg=ronda_generada');
} else {
    header('Location: panel_torneo.php?torneo_id=' . $torneo_id . '&error=' . urlencode($resultado['message'] ?? 'Error al generar ronda'));
}
exit;
