<?php


/**
 * Endpoint para enviar invitaciones desde el formulario de clubes
 * Versión simplificada sin dependencia de email ni PDF complejo
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';

try {
    // Verificar autenticación
    Auth::requireRole(['admin_general', 'admin_torneo']);
    
    // Validar método HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido. Se requiere POST.');
    }
    
    // Obtener datos del POST
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    
    if (!$input) {
        throw new Exception('Datos de entrada inválidos');
    }
    
    // Validar campos requeridos
    $torneo_id = (int)($input['torneo_id'] ?? 0);
    $club_id = (int)($input['club_id'] ?? 0);
    $acceso1 = $input['acceso1'] ?? '';
    $acceso2 = $input['acceso2'] ?? '';
    
    if ($torneo_id <= 0 || $club_id <= 0) {
        throw new Exception('Torneo y Club son requeridos');
    }
    
    if (empty($acceso1) || empty($acceso2)) {
        throw new Exception('Fechas de acceso son requeridas');
    }
    
    // Validar rango de fechas
    if (strtotime($acceso2) < strtotime($acceso1)) {
        throw new Exception('La fecha de fin debe ser posterior a la fecha de inicio');
    }
    
    // Verificar que el torneo existe y obtener archivos adjuntos
    $stmt = DB::pdo()->prepare("SELECT id, nombre, afiche, invitacion, normas FROM tournaments WHERE id = ?");
    $stmt->execute([$torneo_id]);
    $tournament = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tournament) {
        throw new Exception('Torneo no encontrado');
    }
    
    // Verificar que el club existe
    $stmt = DB::pdo()->prepare("SELECT id, nombre FROM clubes WHERE id = ?");
    $stmt->execute([$club_id]);
    $club = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$club) {
        throw new Exception('Club no encontrado');
    }
    
    // Verificar si ya existe una invitación para este torneo y club
    $stmt_check = DB::pdo()->prepare("SELECT id FROM invitations WHERE torneo_id = ? AND club_id = ?");
    $stmt_check->execute([$torneo_id, $club_id]);
    $existing_invitation = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    $invitation_id = null;
    
    // Generar token único de 64 caracteres
    $token = bin2hex(random_bytes(32));
    
    if ($existing_invitation) {
        // Actualizar invitación existente con nuevo token
        $stmt_update = DB::pdo()->prepare("
            UPDATE invitations 
            SET acceso1 = ?, acceso2 = ?, token = ?, estado = 'activa', fecha_modificacion = NOW() 
            WHERE id = ?
        ");
        $stmt_update->execute([$acceso1, $acceso2, $token, $existing_invitation['id']]);
        $invitation_id = $existing_invitation['id'];
        $message = 'Invitación actualizada exitosamente';
    } else {
        // Crear nueva invitación con token
        $stmt_insert = DB::pdo()->prepare("
            INSERT INTO invitations (torneo_id, club_id, acceso1, acceso2, token, estado) 
            VALUES (?, ?, ?, ?, ?, 'activa')
        ");
        $stmt_insert->execute([$torneo_id, $club_id, $acceso1, $acceso2, $token]);
        $invitation_id = DB::pdo()->lastInsertId();
        $message = 'Invitación creada exitosamente';
    }
    
    // Preparar URLs de archivos adjuntos si existen
    $archivos = [];
    
    if (!empty($tournament['afiche'])) {
        $archivo_name = str_replace('upload/tournaments/', '', $tournament['afiche']);
        $archivos['afiche'] = [
            'url' => '../public/view_tournament_file.php?file=' . urlencode($archivo_name),
            'nombre' => 'Afiche del Torneo',
            'icono' => '??'
        ];
    }
    
    if (!empty($tournament['invitacion'])) {
        $archivo_name = str_replace('upload/tournaments/', '', $tournament['invitacion']);
        $archivos['invitacion'] = [
            'url' => '../public/view_tournament_file.php?file=' . urlencode($archivo_name),
            'nombre' => 'Invitación Oficial',
            'icono' => '??'
        ];
    }
    
    if (!empty($tournament['normas'])) {
        $archivo_name = str_replace('upload/tournaments/', '', $tournament['normas']);
        $archivos['normas'] = [
            'url' => '../public/view_tournament_file.php?file=' . urlencode($archivo_name),
            'nombre' => 'Normas/Condiciones',
            'icono' => '??'
        ];
    }
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => $message,
        'invitation_id' => $invitation_id,
        'token' => $token,
        'club_nombre' => $club['nombre'],
        'torneo_nombre' => $tournament['nombre'],
        'archivos' => $archivos
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Error en clubs_send_invitation: " . $e->getMessage());
    http_response_code(400);
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

