<?php


// Establecer cabeceras de respuesta JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-TOKEN');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// DEBUG: mostrar errores en respuesta (activar temporalmente durante pruebas)
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../lib/whatsapp_sender.php';

// Convertir errores en excepciones para capturarlos en el catch
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Registro simple de depuración
$__whats_log = __DIR__ . '/../logs/whatsapp_pdf_endpoint.log';
if (!is_dir(dirname($__whats_log))) {
    @mkdir(dirname($__whats_log), 0755, true);
}
file_put_contents($__whats_log, date('Y-m-d H:i:s') . " - HIT endpoint\n", FILE_APPEND);

try {
    // Validar método HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido. Se requiere POST.');
    }

    // Normalizar CSRF desde header si viene por X-CSRF-TOKEN
    $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($headerToken && empty($_POST['csrf_token'])) {
        $_POST['csrf_token'] = $headerToken;
    }

    // Verificar autenticación - permitir acceso a admin_club también
    require_once __DIR__ . '/../config/auth.php';
    Auth::requireRole(['admin_general', 'admin_torneo', 'admin_club']);
    
    // Validar CSRF
    CSRF::validate();

    // Obtener datos del POST
    $raw = file_get_contents('php://input');
    file_put_contents($__whats_log, date('Y-m-d H:i:s') . " - RAW: " . substr((string)$raw,0,500) . "\n", FILE_APPEND);
    $input = json_decode($raw, true);
    
    if (!$input) {
        // Intentar obtener datos del formulario si no es JSON
        $input = $_POST;
    }

    $invitation_id = $input['invitation_id'] ?? null;
    
    if (!$invitation_id) {
        throw new Exception('invitation_id es requerido');
    }

    // Validar que la invitación existe y obtener datos completos
    $stmt = DB::pdo()->prepare("
        SELECT 
            i.*,
            t.nombre as tournament_name,
            t.fechator as tournament_date,
            t.modalidad,
            t.costo,
            t.club_responsable,
            c.nombre as club_name,
            c.delegado as club_delegado,
            c.email as club_email,
            c.telefono as club_telefono,
            c.direccion as club_direccion,
            oc.nombre as organizer_club_name,
            oc.delegado as organizer_delegado,
            oc.telefono as organizer_telefono,
            oc.email as organizer_email,
            oc.direccion as organizer_direccion
        FROM invitations i
        LEFT JOIN tournaments t ON i.torneo_id = t.id
        LEFT JOIN clubes c ON i.club_id = c.id
        LEFT JOIN clubes oc ON t.club_responsable = oc.id
        WHERE i.id = ?
    ");
    
    $stmt->execute([$invitation_id]);
    $invitation_data = $stmt->fetch();
    
    if (!$invitation_data) {
        throw new Exception('Invitación no encontrada con ID: ' . $invitation_id);
    }

    // Validar teléfono receptor
    $receiver_phone = $invitation_data['club_telefono'] ?? null;
    if (!$receiver_phone) {
        throw new Exception('Teléfono del club invitado no configurado');
    }

    // Generar WhatsApp con PDF
    $result = WhatsAppSender::generateInvitationWithPDF($invitation_data);
    file_put_contents($__whats_log, date('Y-m-d H:i:s') . " - RESULT: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    
    if (!$result['success']) {
        throw new Exception($result['error']);
      }

    echo json_encode([
        'success' => true,
        'message' => 'WhatsApp con PDF generado correctamente',
        'data' => $result
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Error enviando WhatsApp PDF: " . $e->getMessage());
    
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_UNESCAPED_UNICODE);
}
?>



