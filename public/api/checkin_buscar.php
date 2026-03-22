<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__, 2);
require_once $root . '/config/bootstrap.php';
require_once $root . '/app/Database/Connection.php';
require_once $root . '/app/Database/ConnectionException.php';
require_once $root . '/app/Core/AtletaService.php';
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
$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
if ($torneoId <= 0 || $q === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Parámetros incompletos'], JSON_UNESCAPED_UNICODE);
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

$padron = Connection::getSecondaryOptional();
$svc = new AtletaService($pdo, $padron);

$maestro = [];
$digits = AtletaService::normalizarDocumentoNumerico($q);
if (strlen($digits) >= 4) {
    $maestro = $svc->buscar($q);
    if (count($maestro) > 3) {
        $maestro = array_slice($maestro, 0, 3);
    }
}

$padronData = $padron !== null ? $svc->consultarPadron($q) : null;

echo json_encode([
    'ok' => true,
    'maestro' => $maestro,
    'padron' => $padronData,
    'sugerencia_registro' => $padronData !== null && $maestro === [],
], JSON_UNESCAPED_UNICODE);
