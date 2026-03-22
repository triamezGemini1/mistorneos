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

$admin = mn_admin_require_json();

$scope = mn_admin_torneo_query_scope();
if ($scope === false) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Sin organización en sesión.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$torneoId = isset($_GET['torneo_id']) ? (int) $_GET['torneo_id'] : 0;
if ($torneoId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
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

$n = TournamentEngineService::countRatificados($pdo, $torneoId);
$puede = TournamentEngineService::puedeGenerarRonda1($pdo, $torneoId);

echo json_encode([
    'ok' => true,
    'torneo_id' => $torneoId,
    'tipo_torneo' => (string) ($t['tipo_torneo'] ?? 'individual'),
    'ratificados' => $n,
    'minimo_individual' => 8,
    'puede_generar_ronda1' => $puede,
], JSON_UNESCAPED_UNICODE);
