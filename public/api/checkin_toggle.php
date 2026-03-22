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

$inscritoId = isset($input['inscrito_id']) ? (int) $input['inscrito_id'] : 0;
$campo = isset($input['campo']) ? (string) $input['campo'] : '';
$valor = isset($input['valor']) ? (int) $input['valor'] : 0;
$valor = $valor ? 1 : 0;

if ($inscritoId <= 0 || !in_array($campo, ['ratificado', 'presente_sitio'], true)) {
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

$tabla = getenv('DB_AUTH_TABLE') ?: 'usuarios';
$tabla = in_array(strtolower(trim((string) $tabla)), ['usuarios', 'users'], true) ? strtolower(trim((string) $tabla)) : 'usuarios';

$sql = "SELECT i.id, i.torneo_id FROM inscritos i WHERE i.id = ? LIMIT 1";
$st = $pdo->prepare($sql);
$st->execute([$inscritoId]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if ($row === false) {
    http_response_code(404);
    echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

$t = TournamentEngineService::getTorneo($pdo, (int) $row['torneo_id'], $scope);
if ($t === null || !OrganizacionService::adminPuedeGestionarTorneo($admin, $t)) {
    http_response_code(403);
    echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

$col = $campo === 'ratificado' ? 'ratificado' : 'presente_sitio';
try {
    $up = $pdo->prepare("UPDATE inscritos SET `{$col}` = ? WHERE id = ?");
    $up->execute([$valor, $inscritoId]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Columna no disponible. Ejecute sql/tournament_engine_v1.sql'], JSON_UNESCAPED_UNICODE);
    exit;
}

$torneoId = (int) $row['torneo_id'];
echo json_encode([
    'ok' => true,
    'watcher' => [
        'ratificados' => TournamentEngineService::countRatificados($pdo, $torneoId),
        'puede_ronda1' => TournamentEngineService::puedeGenerarRonda1($pdo, $torneoId),
    ],
], JSON_UNESCAPED_UNICODE);
