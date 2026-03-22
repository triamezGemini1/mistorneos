<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/mock_mail_sender.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Verificar autenticación - permitir acceso a admin_club también
    Auth::requireRole(['admin_general', 'admin_torneo', 'admin_club']);
    
    // Obtener invitation_id y correo destino opcional
    $invitation_id = (int)($_POST['invitation_id'] ?? $_GET['invitation_id'] ?? 0);
    $to_override = trim((string)($_POST['to'] ?? $_GET['to'] ?? ''));
    
    if ($invitation_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID de invitación inválido']);
        exit;
    }
    
    // Verificar si se puede enviar correo (simulación)
    if (!MockMailSender::canSendEmail()) {
        echo json_encode(['success' => false, 'error' => 'Sistema de correo no disponible.']);
        exit;
    }
    
    // Obtener datos de la invitación
    $invitation_data = MockMailSender::getInvitationDataForEmail($invitation_id);
    
    if (!$invitation_data) {
        echo json_encode(['success' => false, 'error' => 'Invitación no encontrada']);
        exit;
    }
    
    // Determinar correo destino (override si se envía "to")
    if ($to_override !== '') {
        $invitation_data['club_email'] = $to_override;
        if (empty($invitation_data['club_delegado'])) {
            $invitation_data['club_delegado'] = 'Destinatario de prueba';
        }
    }
    
    // Verificar que exista correo destino
    if (empty($invitation_data['club_email'])) {
        echo json_encode(['success' => false, 'error' => 'No hay correo destino. Configure email del club o use parámetro "to"']);
        exit;
    }
    
    // Enviar correo (simulación)
    $result = MockMailSender::sendInvitationEmail($invitation_data);
    
    if ($result['success']) {
        // Actualizar fecha de envío
        $stmt = DB::pdo()->prepare("UPDATE invitations SET fecha_modificacion = NOW() WHERE id = ?");
        $stmt->execute([$invitation_id]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Correo enviado exitosamente a: ' . $invitation_data['club_email']
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => $result['error']]);
    }
    
} catch (Exception $e) {
    error_log("Error en send_invitation_email: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error interno: ' . $e->getMessage()]);
}
?>
