<?php
/**
 * API Guardar Comentario - Para SPA
 * POST: Acepta JSON, retorna JSON
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/csrf.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Validar CSRF para API (header X-CSRF-Token)
CSRF::validateApi();

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$nombre = trim($input['nombre'] ?? '');
$email = trim($input['email'] ?? '');
$tipo = $input['tipo'] ?? 'comentario';
$contenido = trim($input['contenido'] ?? '');
$calificacion = isset($input['calificacion']) ? (int)$input['calificacion'] : null;

$errors = [];
$pdo = DB::pdo();
$user = Auth::user();

if ($user) {
    $usuario_id = Auth::id() ?: null;
    $nombre = $user['nombre'] ?? $user['username'] ?? '';
    $email = $user['email'] ?? $email;
} else {
    if (empty($nombre)) $errors[] = 'El nombre es requerido';
    elseif (strlen($nombre) < 2) $errors[] = 'El nombre debe tener al menos 2 caracteres';
    elseif (strlen($nombre) > 100) $errors[] = 'El nombre no puede exceder 100 caracteres';
    $usuario_id = null;
}

if (!in_array($tipo, ['comentario', 'sugerencia', 'testimonio'])) $tipo = 'comentario';

if (empty($contenido)) $errors[] = 'El contenido del comentario es requerido';
elseif (strlen($contenido) < 10) $errors[] = 'El comentario debe tener al menos 10 caracteres';
elseif (strlen($contenido) > 2000) $errors[] = 'El comentario no puede exceder 2000 caracteres';

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'El email proporcionado no es válido';
}

if ($calificacion !== null && ($calificacion < 1 || $calificacion > 5)) $calificacion = null;

$ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
try {
    $check_ip = $pdo->query("SHOW COLUMNS FROM comentariossugerencias LIKE 'ip_address'")->fetch();
    if ($check_ip && $ip_address) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM comentariossugerencias WHERE ip_address = ? AND fecha_creacion > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $stmt->execute([$ip_address]);
        if ($stmt->fetchColumn() >= 5) {
            $errors[] = 'Has enviado demasiados comentarios recientemente. Por favor espera un momento.';
        }
    }
} catch (Exception $e) {}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => $errors], JSON_UNESCAPED_UNICODE);
    exit;
}

$contenido = htmlspecialchars($contenido, ENT_QUOTES, 'UTF-8');

try {
    $columns_check = $pdo->query("SHOW COLUMNS FROM comentariossugerencias")->fetchAll(PDO::FETCH_COLUMN);
    $has_ip_address = in_array('ip_address', $columns_check);
    $has_user_agent = in_array('user_agent', $columns_check);
} catch (Exception $e) {
    $has_ip_address = $has_user_agent = false;
}

$fields = ['usuario_id', 'nombre', 'email', 'tipo', 'contenido', 'calificacion', 'estatus'];
$values = [$usuario_id, $nombre, $email ?: null, $tipo, $contenido, $calificacion, 'pendiente'];
if ($has_ip_address && $ip_address) {
    $fields[] = 'ip_address';
    $values[] = $ip_address;
}
if ($has_user_agent && ($user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null)) {
    $fields[] = 'user_agent';
    $values[] = $user_agent;
}

$placeholders = implode(', ', array_fill(0, count($values), '?'));
$stmt = $pdo->prepare("INSERT INTO comentariossugerencias (" . implode(', ', $fields) . ") VALUES ($placeholders)");
$stmt->execute($values);

echo json_encode([
    'success' => true,
    'message' => 'Tu comentario ha sido enviado y será revisado antes de publicarse.'
], JSON_UNESCAPED_UNICODE);
