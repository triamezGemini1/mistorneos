<?php
/**
 * API para obtener datos de invitación
 */



header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';

try {
    // Verificar autenticación
    Auth::requireRole(['admin_general', 'admin_torneo']);
    
    // Obtener ID
    $id = (int)($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }
    
    // Obtener datos completos
    $stmt = DB::pdo()->prepare("
        SELECT 
            i.id,
            i.torneo_id,
            i.club_id,
            i.token,
            i.estado,
            i.acceso1,
            i.acceso2,
            i.fecha_creacion,
            i.fecha_modificacion,
            t.nombre as torneo_nombre,
            t.fechator as torneo_fecha,
            ci.nombre as club_invitado_nombre,
            ci.delegado as club_invitado_delegado,
            ci.telefono as club_invitado_telefono,
            cr.nombre as club_responsable_nombre
        FROM invitations i
        INNER JOIN tournaments t ON i.torneo_id = t.id
        INNER JOIN clubes ci ON i.club_id = ci.id
        LEFT JOIN clubes cr ON t.club_responsable = cr.id
        WHERE i.id = ?
    ");
    $stmt->execute([$id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$data) {
        throw new Exception('Invitación no encontrada');
    }
    
    echo json_encode([
        'success' => true,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}










