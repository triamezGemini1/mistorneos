<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/whatsapp_sender.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Verificar autenticación - permitir acceso a admin_club también
    Auth::requireRole(['admin_general', 'admin_torneo', 'admin_club']);
    
    // Obtener invitation_id y teléfono
    $invitation_id = (int)($_POST['invitation_id'] ?? $_GET['invitation_id'] ?? 0);
    $phone_override = trim((string)($_POST['phone'] ?? $_GET['phone'] ?? ''));
    
    if ($invitation_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID de invitación inválido']);
        exit;
    }
    
    // Obtener datos de la invitación primero para usar el teléfono por defecto
    $invitation_data = WhatsAppSender::getInvitationDataForWhatsApp($invitation_id);
    
    if (!$invitation_data) {
        echo json_encode(['success' => false, 'error' => 'Invitación no encontrada']);
        exit;
    }
    
    // Usar teléfono del club invitado por defecto, o el override si se proporciona
    $phone = !empty($phone_override) ? $phone_override : ($invitation_data['club_telefono'] ?? '');
    
    if (empty($phone)) {
        echo json_encode(['success' => false, 'error' => 'No hay teléfono configurado para el club invitado. Use parámetro "phone" o configure teléfono del club.']);
        exit;
    }
    
    // Validar número de teléfono
    if (!WhatsAppSender::validatePhone($phone)) {
        echo json_encode(['success' => false, 'error' => 'Número de teléfono inválido']);
        exit;
    }
    
    // Enviar WhatsApp
    $result = WhatsAppSender::sendInvitationWhatsApp($invitation_data, $phone);
    
    if ($result['success']) {
        // Actualizar fecha de envío
        $stmt = DB::pdo()->prepare("UPDATE invitations SET fecha_modificacion = NOW() WHERE id = ?");
        $stmt->execute([$invitation_id]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Enlace de WhatsApp generado para: ' . WhatsAppSender::formatPhone($phone),
            'whatsapp_link' => $result['whatsapp_link'],
            'phone' => $result['phone']
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => $result['error']]);
    }
    
} catch (Exception $e) {
    error_log("Error en send_invitation_whatsapp: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error interno: ' . $e->getMessage()]);
}
?>
