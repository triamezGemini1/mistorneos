<?php
/**
 * API para cambiar el estatus de un inscrito
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../lib/InscritosHelper.php';

header('Content-Type: application/json; charset=utf-8');

Auth::requireRole(['admin_general', 'admin_torneo', 'admin_club']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Validar CSRF
$csrf_token = $_POST['csrf_token'] ?? '';
$session_token = $_SESSION['csrf_token'] ?? '';
if (!$csrf_token || !$session_token || !hash_equals($session_token, $csrf_token)) {
    echo json_encode(['success' => false, 'error' => 'Token CSRF inválido']);
    exit;
}

try {
    $inscripcion_id = (int)($_POST['inscripcion_id'] ?? 0);
    $nuevo_estatus_raw = $_POST['estatus'] ?? 0;
    $torneo_id = (int)($_POST['torneo_id'] ?? 0);
    $nuevo_estatus = is_numeric($nuevo_estatus_raw) ? (int)$nuevo_estatus_raw : 0;
    
    if ($inscripcion_id <= 0 || $torneo_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
        exit;
    }
    
    if (!InscritosHelper::isValidEstatus($nuevo_estatus)) {
        echo json_encode(['success' => false, 'error' => 'Estatus inválido']);
        exit;
    }
    
    // Verificar acceso al torneo
    if (!Auth::canAccessTournament($torneo_id)) {
        echo json_encode(['success' => false, 'error' => 'No tiene permisos para acceder a este torneo']);
        exit;
    }
    
    $pdo = DB::pdo();
    
    // Verificar que la inscripción existe y pertenece al torneo
    $stmt = $pdo->prepare("SELECT id FROM inscritos WHERE id = ? AND torneo_id = ?");
    $stmt->execute([$inscripcion_id, $torneo_id]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Inscripción no encontrada']);
        exit;
    }
    
    // Actualizar estatus (columna INT en producción: 0=pendiente, 1=confirmado, 4=retirado)
    $stmt = $pdo->prepare("UPDATE inscritos SET estatus = ? WHERE id = ? AND torneo_id = ?");
    $stmt->execute([$nuevo_estatus, $inscripcion_id, $torneo_id]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Estatus actualizado exitosamente',
        'estatus' => $nuevo_estatus,
        'estatus_texto' => InscritosHelper::getEstatusFormateado($nuevo_estatus),
        'estatus_clase' => InscritosHelper::getEstatusClaseCSS($nuevo_estatus)
    ]);
    
} catch (Exception $e) {
    error_log("Error en cambiar_estatus: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}

