<?php

declare(strict_types=1);

/**
 * Disparador de Ronda 1 (placeholder): valida watcher y deja listo el hook para emparejamientos reales.
 */

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__, 2);
require_once $root . '/config/bootstrap.php';
require_once $root . '/app/Database/Connection.php';
require_once $root . '/app/Database/ConnectionException.php';
require_once $root . '/app/Core/TournamentEngineService.php';
require_once $root . '/app/Core/OrganizacionService.php';
require_once $root . '/app/Helpers/AdminApi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

$admin = mn_admin_require_json();

$scope = mn_admin_torneo_query_scope();
if ($scope === false) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Sin organización en sesión.'], JSON_UNESCAPED_UNICODE);
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
    echo json_encode(['ok' => false, 'message' => 'CSRF'], JSON_UNESCAPED_UNICODE);
    exit;
}

$torneoId = isset($input['torneo_id']) ? (int) $input['torneo_id'] : 0;
if ($torneoId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'torneo_id inválido'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = Connection::get();
} catch (ConnectionException $e) {
    http_response_code(503);
    echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

$t = TournamentEngineService::getTorneo($pdo, $torneoId, $scope);
if ($t === null || !OrganizacionService::adminPuedeGestionarTorneo($admin, $t)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Sin permiso'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!TournamentEngineService::puedeGenerarRonda1($pdo, $torneoId)) {
    http_response_code(409);
    echo json_encode([
        'ok' => false,
        'message' => 'Aún no se cumplen las condiciones (individual, ≥8 ratificados).',
        'ratificados' => TournamentEngineService::countRatificados($pdo, $torneoId),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'ok' => true,
    'message' => 'Condiciones cumplidas. La generación de mesas/rondas se conectará al módulo legacy en el siguiente paso.',
    'integracion_pendiente' => true,
    'torneo_id' => $torneoId,
], JSON_UNESCAPED_UNICODE);
