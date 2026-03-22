<?php

declare(strict_types=1);

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
$usuarioId = isset($input['id_usuario']) ? (int) $input['id_usuario'] : 0;
if ($torneoId <= 0 || $usuarioId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Datos inválidos'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = Connection::get();
} catch (ConnectionException $e) {
    http_response_code(503);
    exit;
}

$t = TournamentEngineService::getTorneo($pdo, $torneoId, $scope);
if ($t === null || !OrganizacionService::adminPuedeGestionarTorneo($admin, $t)) {
    http_response_code(403);
    echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

$r = TournamentEngineService::inscribirUsuarioEnTorneo($pdo, $torneoId, $usuarioId);
echo json_encode([
    'ok' => $r['ok'],
    'error' => $r['error'] ?? null,
    'inscrito_id' => $r['inscrito_id'] ?? null,
], JSON_UNESCAPED_UNICODE);
