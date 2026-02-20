<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/pdf_generator.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Verificar autenticación - permitir acceso a admin_club también
    Auth::requireRole(['admin_general', 'admin_torneo', 'admin_club']);
    
    // Obtener invitation_id
    $invitation_id = (int)($_POST['invitation_id'] ?? $_GET['invitation_id'] ?? 0);
    
    if ($invitation_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID de invitación inválido']);
        exit;
    }
    
    // Generar PDF
    $result = PDFGenerator::generateInvitationPDF($invitation_id);
    
    if ($result['success']) {
        // Actualizar fecha de modificación
        $stmt = DB::pdo()->prepare("UPDATE invitations SET fecha_modificacion = NOW() WHERE id = ?");
        $stmt->execute([$invitation_id]);
        
        $direct_url = app_base_url() . '/' . $result['pdf_path'];
        $viewer_url = app_base_url() . '/view_file.php?file=' . urlencode($result['pdf_path']);
        echo json_encode([
            'success' => true,
            'message' => 'PDF generado correctamente',
            'pdf_path' => $result['pdf_path'],
            'pdf_url' => $direct_url,
            'viewer_url' => $viewer_url
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => $result['error']]);
    }
    
} catch (Exception $e) {
    error_log("Error en generate_invitation_pdf: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error interno: ' . $e->getMessage()]);
}
?>



