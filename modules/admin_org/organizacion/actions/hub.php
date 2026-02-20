<?php
/**
 * Action: Hub de Organización - Resumen para admin_club
 * Carga datos de la organización del usuario y delega a la vista.
 */

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../../lib/app_helpers.php';

$current_user = Auth::user();
$organizacion_id = Auth::getUserOrganizacionId();

if (!$organizacion_id) {
    header('Location: index.php?page=mi_organizacion&error=' . urlencode('No tiene una organización asignada'));
    exit;
}

$pdo = DB::pdo();

$stmt = $pdo->prepare("
    SELECT o.*, e.nombre as entidad_nombre
    FROM organizaciones o
    LEFT JOIN entidad e ON o.entidad = e.id
    WHERE o.id = ? AND o.estatus = 1
");
$stmt->execute([$organizacion_id]);
$organizacion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$organizacion) {
    header('Location: index.php?page=mi_organizacion&error=' . urlencode('Organización no encontrada'));
    exit;
}

// Estadísticas
$stmt = $pdo->prepare("SELECT COUNT(*) FROM clubes WHERE organizacion_id = ? AND estatus = 1");
$stmt->execute([$organizacion_id]);
$stats_clubes = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tournaments WHERE club_responsable = ? AND fechator >= CURDATE() AND estatus = 1");
$stmt->execute([$organizacion_id]);
$stats_torneos_activos = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM usuarios u
    INNER JOIN clubes c ON u.club_id = c.id
    WHERE c.organizacion_id = ? AND c.estatus = 1 AND u.role = 'usuario' AND u.status = 0
");
$stmt->execute([$organizacion_id]);
$stats_afiliados = (int)$stmt->fetchColumn();

$stats = [
    'clubes' => $stats_clubes,
    'torneos_activos' => $stats_torneos_activos,
    'afiliados' => $stats_afiliados,
];

include __DIR__ . '/../views/hub.php';
