<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__, 2);
require_once $root . '/config/bootstrap.php';
require_once $root . '/app/Database/Connection.php';
require_once $root . '/app/Core/AtletaService.php';

$cedula = isset($_GET['cedula']) ? trim((string) $_GET['cedula']) : '';

if ($cedula === '') {
    echo json_encode(['ok' => true, 'encontrado' => false, 'motivo' => 'cedula_vacia'], JSON_UNESCAPED_UNICODE);
    exit;
}

$padron = Connection::getSecondaryOptional();
if ($padron === null) {
    echo json_encode([
        'ok' => true,
        'encontrado' => false,
        'motivo' => 'padron_no_configurado',
        'message' => 'La consulta al padrón requiere DB_SECONDARY_* en .env.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdoOp = Connection::get();
} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'encontrado' => false, 'message' => 'Servicio no disponible.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$svc = new AtletaService($pdoOp, $padron);
$datos = $svc->consultarPadron($cedula);

if ($datos === null) {
    echo json_encode(['ok' => true, 'encontrado' => false, 'motivo' => 'no_encontrado'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'ok' => true,
    'encontrado' => true,
    'datos' => [
        'nombre_completo' => $datos['nombre_completo'],
        'nombres' => $datos['nombres'],
        'apellidos' => $datos['apellidos'],
        'cedula' => $datos['cedula'],
        'nacionalidad' => $datos['nacionalidad'],
        'fecha_nacimiento' => $datos['fecha_nacimiento'],
        'sexo' => $datos['sexo'],
    ],
], JSON_UNESCAPED_UNICODE);
