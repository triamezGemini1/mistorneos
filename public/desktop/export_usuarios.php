<?php
/**
 * Guardar Cambios: envía los estados is_active de los administradores (sync_status = 0) al servidor remoto.
 * Tras éxito redirige a usuarios.php. Así el bloqueo es efectivo en la web de inmediato.
 */
declare(strict_types=1);

require_once __DIR__ . '/db_local.php';

if (file_exists(__DIR__ . '/config_sync.php')) {
    require __DIR__ . '/config_sync.php';
}
$pushUrl = defined('SYNC_PUSH_URL') ? SYNC_PUSH_URL : (getenv('SYNC_PUSH_URL') ?: '');
$apiKey = defined('SYNC_API_KEY') ? SYNC_API_KEY : (getenv('SYNC_API_KEY') ?: '');
$sslVerify = defined('SYNC_SSL_VERIFY') ? (bool) SYNC_SSL_VERIFY : false;

if ($pushUrl === '' || $apiKey === '') {
    $msg = 'Configura SYNC_PUSH_URL y SYNC_API_KEY en config_sync.php.';
    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, $msg . PHP_EOL);
        exit(1);
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Guardar cambios</title></head><body><p>' . htmlspecialchars($msg) . '</p><a href="usuarios.php">Volver</a></body></html>';
    exit;
}

$pdo = DB_Local::pdo();
$stmt = $pdo->query("
    SELECT id, uuid, nombre, cedula, nacionalidad, sexo, fechnac, email, username, club_id, entidad, status, role, last_updated, sync_status, is_active
    FROM usuarios
    WHERE role IN ('admin_general','admin_torneo','admin_club','operador') AND sync_status = 0
    ORDER BY id
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) {
    if (php_sapi_name() === 'cli') {
        echo "No hay cambios pendientes de subir.\n";
        exit(0);
    }
    header('Location: usuarios.php?export=0');
    exit;
}

$payload = ['jugadores' => $rows];
$json = json_encode($payload);

$ch = curl_init($pushUrl);
$opts = [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $json,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'X-API-Key: ' . trim($apiKey)],
];
if (!$sslVerify) {
    $opts[CURLOPT_SSL_VERIFYPEER] = false;
    $opts[CURLOPT_SSL_VERIFYHOST] = 0;
}
curl_setopt_array($ch, $opts);
$response = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

$isCli = php_sapi_name() === 'cli';

if ($response === false || $err !== '') {
    $msg = 'Error de conexión: ' . ($err ?: 'No se pudo conectar al servidor.');
    if ($isCli) {
        fwrite(STDERR, $msg . PHP_EOL);
        exit(1);
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Guardar cambios</title></head><body><p>' . htmlspecialchars($msg) . '</p><a href="usuarios.php">Volver</a></body></html>';
    exit;
}

$data = json_decode($response, true);

if ($httpCode >= 400 || !is_array($data) || empty($data['ok'])) {
    $msg = $data['message'] ?? 'El servidor respondió con error (HTTP ' . $httpCode . ').';
    if ($isCli) {
        fwrite(STDERR, $msg . PHP_EOL);
        exit(1);
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Guardar cambios</title></head><body><p>' . htmlspecialchars($msg) . '</p><a href="usuarios.php">Volver</a></body></html>';
    exit;
}

$ids = array_column($rows, 'id');
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$pdo->prepare("UPDATE usuarios SET sync_status = 1 WHERE id IN ({$placeholders})")->execute($ids);

$total = count($rows);
if ($isCli) {
    echo "Guardar cambios: {$total} registro(s) enviado(s) a la web.\n";
    exit(0);
}
header('Location: usuarios.php?export=1&n=' . $total);
exit;
