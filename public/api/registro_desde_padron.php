<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__, 2);
require_once $root . '/config/bootstrap.php';
require_once $root . '/app/Database/ConnectionException.php';
require_once $root . '/app/Database/Connection.php';
require_once $root . '/app/Core/AtletaService.php';
require_once $root . '/app/Core/RegistroDesdePadronService.php';
require_once $root . '/app/Core/TournamentEngineService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw !== false ? $raw : '', true);
if (!is_array($input)) {
    $input = $_POST;
}

$token = isset($input['csrf_token']) ? (string) $input['csrf_token'] : '';
if (!csrf_validate($token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'csrf', 'message' => 'Sesión de seguridad inválida. Recargue la página.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $operativa = Connection::get();
} catch (ConnectionException $e) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'message' => 'Base de datos no disponible.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$padron = Connection::getSecondaryOptional();
$atletaSvc = new AtletaService($operativa, $padron);

$resultado = RegistroDesdePadronService::registrar($operativa, $padron, $atletaSvc, $input);

if ($resultado['ok']) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    unset($_SESSION['admin_user']);

    $_SESSION['user'] = [
        'id' => (int) $resultado['user_id'],
        'username' => (string) $resultado['username'],
        'email' => (string) $resultado['email'],
        'role' => (string) $resultado['role'],
        'nombre' => (string) $resultado['nombre'],
        'cedula' => (string) $resultado['cedula'],
        'nacionalidad' => (string) $resultado['nacionalidad'],
    ];

    if (!empty($_SESSION['invitar_torneo_id'])) {
        $tid = (int) $_SESSION['invitar_torneo_id'];
        unset($_SESSION['invitar_torneo_id']);
        if ($tid > 0) {
            TournamentEngineService::inscribirUsuarioEnTorneo($operativa, $tid, (int) $resultado['user_id']);
        }
    }

    echo json_encode([
        'ok' => true,
        'user_id' => $resultado['user_id'],
        'message' => 'Bienvenido a mistorneos. Redirigiendo a su panel…',
        'redirect' => 'dashboard.php',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$map = [
    'padron_no_disponible' => 'El padrón no está disponible. No se puede completar el registro.',
    'cedula_invalida' => 'Cédula no válida.',
    'email_invalido' => 'Correo electrónico no válido.',
    'password_debil' => 'La contraseña debe tener al menos 8 caracteres.',
    'no_en_padron' => 'No se encontró la cédula en el padrón. Verifique el documento.',
    'cedula_duplicada' => 'Esa cédula ya está registrada en mistorneos.',
    'email_duplicado' => 'Ese correo ya está registrado.',
    'duplicado_bd' => 'No se pudo registrar: datos duplicados.',
    'error_insercion' => 'No se pudo completar el registro. Intente más tarde.',
];

$err = $resultado['error'];
$msg = $map[$err] ?? 'No se pudo registrar.';

http_response_code(400);
echo json_encode([
    'ok' => false,
    'error' => $err,
    'message' => $msg,
], JSON_UNESCAPED_UNICODE);
