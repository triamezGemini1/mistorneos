<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/config/bootstrap.php';
require_once $root . '/app/Database/ConnectionException.php';
require_once $root . '/app/Database/Connection.php';
require_once $root . '/app/Core/TournamentEngineService.php';

$slug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';
if ($slug === '' || strlen($slug) > 150 || !preg_match('/^[a-zA-Z0-9\-]+$/', $slug)) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Enlace no válido</title></head><body><p>Enlace de invitación no válido.</p></body></html>';
    exit;
}

try {
    $pdo = Connection::get();
} catch (ConnectionException $e) {
    http_response_code(503);
    echo 'Servicio no disponible.';
    exit;
}

$torneo = TournamentEngineService::findTorneoBySlug($pdo, $slug);
if ($torneo === null) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Torneo no encontrado</title></head><body><p>No existe un torneo con este enlace. Verifique la URL.</p></body></html>';
    exit;
}

$torneoId = (int) $torneo['id'];
$nombreTorneo = (string) ($torneo['nombre'] ?? 'Torneo');

if (AuthHelper::isLoggedIn()) {
    $uid = (int) (AuthHelper::currentUser()['id'] ?? 0);
    if ($uid > 0) {
        $r = TournamentEngineService::inscribirUsuarioEnTorneo($pdo, $torneoId, $uid);
        $q = $r['ok'] ? 'inscrito=1' : 'inscrito=0';
        header('Location: dashboard.php?' . $q . '&torneo=' . $torneoId, true, 303);
        exit;
    }
}

$_SESSION['invitar_torneo_id'] = $torneoId;

$script = $_SERVER['SCRIPT_NAME'] ?? '';
$publicPrefix = str_contains($script, '/public/') ? '' : 'public/';
$landing = $publicPrefix . 'index.php?invitacion=1&torneo=' . $torneoId;

header('Location: ' . $landing . '#mn-registro-padron', true, 303);
exit;
