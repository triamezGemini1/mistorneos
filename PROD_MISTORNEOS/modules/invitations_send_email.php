<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/email_sender.php';

header('Content-Type: application/json; charset=utf-8');

try {
	Auth::requireRole(['admin_general','admin_torneo','admin_club']);
	
	// Validación CSRF no destructiva (JSON)
	$postedToken = $_POST['csrf_token'] ?? '';
	$headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
	$sessionToken = $_SESSION['csrf_token'] ?? '';
	if (!$sessionToken || (!$postedToken && !$headerToken) || !hash_equals($sessionToken, $postedToken ?: $headerToken)) {
		http_response_code(400);
		echo json_encode(['ok' => false, 'error' => 'CSRF token inválido']);
		exit;
	}

	// Debug: Log received data
	error_log("POST data: " . print_r($_POST, true));
	error_log("invitation_id: " . ($_POST['invitation_id'] ?? 'NOT_SET'));
	
	$invitationId = (int)($_POST['invitation_id'] ?? 0);
	if ($invitationId <= 0) {
		echo json_encode(['ok' => false, 'error' => 'invitation_id inválido: ' . ($_POST['invitation_id'] ?? 'NOT_SET')]);
		exit;
	}
	$data = EmailSender::getInvitationDataForEmail($invitationId);
	if (!$data) {
		echo json_encode(['ok' => false, 'error' => 'Invitación no encontrada']);
		exit;
	}
	if (empty($data['club_email'])) {
		echo json_encode(['ok' => false, 'error' => 'El club no tiene email configurado']);
		exit;
	}
	$sent = EmailSender::sendInvitationEmail($data);
	echo json_encode(['ok' => (bool)$sent]);
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
