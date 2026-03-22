<?php

declare(strict_types=1);

/**
 * PNG (o SVG si no hay GD) del QR con URL del perfil /atleta.php?id=
 * Solo admin con permiso sobre el torneo e inscrito válido.
 */

$root = dirname(__DIR__, 2);
require_once $root . '/config/bootstrap.php';
require_once $root . '/app/Database/Connection.php';
require_once $root . '/app/Database/ConnectionException.php';
require_once $root . '/app/Core/TournamentEngineService.php';
require_once $root . '/app/Core/OrganizacionService.php';
require_once $root . '/app/Helpers/AdminApi.php';
require_once $root . '/app/Helpers/PublicUrl.php';
require_once $root . '/vendor/autoload.php';

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

if (mn_admin_session() === null) {
    http_response_code(401);
    exit;
}

$scope = mn_admin_torneo_query_scope();
if ($scope === false) {
    http_response_code(403);
    exit;
}

$torneoId = isset($_GET['torneo_id']) ? (int) $_GET['torneo_id'] : 0;
$usuarioId = isset($_GET['usuario_id']) ? (int) $_GET['usuario_id'] : 0;
if ($torneoId <= 0 || $usuarioId <= 0) {
    http_response_code(400);
    exit;
}

try {
    $pdo = Connection::get();
} catch (ConnectionException $e) {
    http_response_code(503);
    exit;
}

$admin = mn_admin_session();
$t = TournamentEngineService::getTorneo($pdo, $torneoId, $scope);
if ($t === null || !OrganizacionService::adminPuedeGestionarTorneo($admin, $t)) {
    http_response_code(403);
    exit;
}

$st = $pdo->prepare('SELECT 1 FROM inscritos WHERE torneo_id = ? AND id_usuario = ? LIMIT 1');
$st->execute([$torneoId, $usuarioId]);
if ($st->fetchColumn() === false) {
    http_response_code(404);
    exit;
}

$url = mn_atleta_perfil_url($usuarioId);
if ($url === '') {
    http_response_code(500);
    exit;
}

$pngOk = extension_loaded('gd');

try {
    if ($pngOk) {
        $opts = new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'scale' => 6,
            'imageBase64' => false,
        ]);
        header('Content-Type: image/png');
        header('Cache-Control: private, max-age=3600');
        echo (new QRCode($opts))->render($url);
        exit;
    }
} catch (Throwable $e) {
    error_log('atleta_qr_png PNG: ' . $e->getMessage());
}

try {
    $opts = new QROptions([
        'outputType' => QRCode::OUTPUT_MARKUP_SVG,
        'scale' => 5,
    ]);
    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Cache-Control: private, max-age=3600');
    echo (new QRCode($opts))->render($url);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('atleta_qr_png SVG: ' . $e->getMessage());
}
