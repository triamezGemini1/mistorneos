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
    echo json_encode(['ok' => false, 'message' => 'torneo_id requerido'], JSON_UNESCAPED_UNICODE);
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
    echo json_encode(['ok' => false, 'message' => 'Sin permiso para este torneo.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$tabla = getenv('DB_AUTH_TABLE') ?: 'usuarios';
$tabla = in_array(strtolower(trim((string) $tabla)), ['usuarios', 'users'], true) ? strtolower(trim((string) $tabla)) : 'usuarios';

$sql = <<<SQL
    SELECT i.id, i.id_usuario, i.ratificado, i.presente_sitio, i.estatus,
           u.nombre, u.cedula, u.email
    FROM inscritos i
    INNER JOIN `{$tabla}` u ON u.id = i.id_usuario
    WHERE i.torneo_id = ?
    ORDER BY u.nombre ASC
    SQL;

try {
    $st = $pdo->prepare($sql);
    $st->execute([$torneoId]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $sql2 = <<<SQL
        SELECT i.id, i.id_usuario, i.estatus, u.nombre, u.cedula, u.email
        FROM inscritos i
        INNER JOIN `{$tabla}` u ON u.id = i.id_usuario
        WHERE i.torneo_id = ?
        ORDER BY u.nombre ASC
        SQL;
    $st = $pdo->prepare($sql2);
    $st->execute([$torneoId]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['ratificado'] = 0;
        $r['presente_sitio'] = 0;
    }
    unset($r);
}

echo json_encode([
    'ok' => true,
    'inscritos' => $rows,
    'watcher' => [
        'ratificados' => TournamentEngineService::countRatificados($pdo, $torneoId),
        'puede_ronda1' => TournamentEngineService::puedeGenerarRonda1($pdo, $torneoId),
    ],
], JSON_UNESCAPED_UNICODE);
