<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__, 2);
require_once $root . '/config/bootstrap.php';
require_once $root . '/app/Database/ConnectionException.php';
require_once $root . '/app/Database/Connection.php';
require_once $root . '/app/Core/AtletaService.php';

$q = isset($_GET['q']) ? (string) $_GET['q'] : '';

try {
    $pdo = Connection::get();
} catch (ConnectionException $e) {
    http_response_code(503);
    echo json_encode([
        'ok' => false,
        'message' => 'Servicio temporalmente no disponible.',
        'resultados' => [],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $svc = new AtletaService($pdo);
    $resultados = $svc->buscar($q);
} catch (PDOException $e) {
    error_log('buscar_atleta: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode([
        'ok' => false,
        'message' => 'No se pudo consultar la tabla de usuarios.',
        'resultados' => [],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'ok' => true,
    'resultados' => $resultados,
    'total' => count($resultados),
    'fuente' => 'usuarios_maestro',
], JSON_UNESCAPED_UNICODE);
