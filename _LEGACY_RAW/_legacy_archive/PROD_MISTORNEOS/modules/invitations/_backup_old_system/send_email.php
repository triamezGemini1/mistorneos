<?php

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../lib/email_sender.php';
require_once __DIR__ . '/../../lib/validation.php';

Auth::requireRole(['admin_general','admin_torneo','admin_club']);
CSRF::validate();

$invitation_id = V::int($_POST['invitation_id'] ?? 0, 1);

try {
    // Obtener datos de la invitación
    $invitation_data = EmailSender::getInvitationDataForEmail($invitation_id);
    
    if (!$invitation_data) {
        throw new Exception('Invitación no encontrada');
    }
    
    // Verificar que el club tenga email
    if (empty($invitation_data['club_email'])) {
        throw new Exception('El club no tiene email configurado');
    }
    
    // Verificar que se puede enviar correo
    if (!EmailSender::canSendEmail()) {
        throw new Exception('El sistema de correo no está configurado correctamente');
    }
    
    // Enviar correo
    $email_sent = EmailSender::sendInvitationEmail($invitation_data);
    
    if ($email_sent) {
        // Actualizar fecha de envío (opcional)
        $stmt = DB::pdo()->prepare("UPDATE invitations SET fecha_modificacion = NOW() WHERE id = ?");
        $stmt->execute([$invitation_id]);
        
        $success_message = "Correo de invitación enviado exitosamente a: " . $invitation_data['club_email'];
    } else {
        throw new Exception('Error al enviar el correo');
    }
    
} catch (Exception $e) {
    $error_message = "Error al enviar correo: " . $e->getMessage();
}

// Redirigir de vuelta a la lista con mensaje
$redirect_url = "index.php?page=invitations";
if (isset($success_message)) {
    $redirect_url .= "&success=" . urlencode($success_message);
}
if (isset($error_message)) {
    $redirect_url .= "&error=" . urlencode($error_message);
}

header('Location: ' . $redirect_url);
exit;
?>









