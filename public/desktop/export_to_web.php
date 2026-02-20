<?php
/**
 * Exporta al servidor web los jugadores locales con sync_status = 0.
 * Envía POST (JSON) a api/sync_api.php; si responde éxito, marca sync_status = 1 en SQLite.
 */
declare(strict_types=1);

require_once __DIR__ . '/db_local.php';

if (file_exists(__DIR__ . '/config_sync.php')) {
    require __DIR__ . '/config_sync.php';
}
$pushUrl = defined('SYNC_PUSH_URL') ? SYNC_PUSH_URL : (getenv('SYNC_PUSH_URL') ?: '');
$apiKey = defined('SYNC_API_KEY') ? SYNC_API_KEY : (getenv('SYNC_API_KEY') ?: '');

if ($pushUrl === '' || $apiKey === '') {
    $msg = 'Configura SYNC_PUSH_URL y SYNC_API_KEY en desktop/config_sync.php.';
    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, $msg . PHP_EOL);
        exit(1);
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Exportar</title></head><body><p>' . htmlspecialchars($msg) . '</p><a href="index.php">Volver</a></body></html>';
    exit;
}

$pdo = DB_Local::pdo();
$stmt = $pdo->query("SELECT id, uuid, nombre, cedula, nacionalidad, sexo, fechnac, email, username, club_id, entidad, status, role, last_updated, sync_status, is_active, creado_por, fecha_creacion FROM usuarios WHERE sync_status = 0 ORDER BY id");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmtAudit = $pdo->query("SELECT id, usuario_id, accion, detalle, entidad_tipo, entidad_id, organizacion_id, fecha FROM auditoria WHERE sync_status = 0 ORDER BY id");
$auditoriaRows = $stmtAudit->fetchAll(PDO::FETCH_ASSOC);

$payload = ['jugadores' => $rows, 'auditoria' => $auditoriaRows];
$json = json_encode($payload);

$ch = curl_init($pushUrl);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $json,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'X-API-Key: ' . $apiKey,
    ],
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
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
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Exportar</title></head><body><p>' . htmlspecialchars($msg) . '</p><a href="index.php">Volver al inicio</a></body></html>';
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
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Exportar</title></head><body><p>' . htmlspecialchars($msg) . '</p><a href="index.php">Volver al inicio</a></body></html>';
    exit;
}

$ids = array_column($rows, 'id');
if (!empty($ids)) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("UPDATE usuarios SET sync_status = 1 WHERE id IN ({$placeholders})");
    $stmt->execute($ids);
}

$auditoriaIds = array_column($auditoriaRows, 'id');
if (!empty($auditoriaIds)) {
    $ph = implode(',', array_fill(0, count($auditoriaIds), '?'));
    $pdo->prepare("UPDATE auditoria SET sync_status = 1 WHERE id IN ({$ph})")->execute($auditoriaIds);
}

$total = count($rows);
$inserted = (int)($data['inserted'] ?? 0);
$updated = (int)($data['updated'] ?? 0);
$auditoriaSent = count($auditoriaRows);
$auditoriaInserted = (int)($data['auditoria_inserted'] ?? 0);
$message = "Sincronización con la web completada: {$total} registro(s) de jugadores enviado(s) ({$inserted} insertados, {$updated} actualizados).";
if ($auditoriaSent > 0) {
    $message .= " {$auditoriaSent} registro(s) de auditoría subido(s).";
}

$isBackground = $isCli || isset($_GET['background']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

if ($isCli) {
    echo $message . PHP_EOL;
    exit(0);
}
if ($isBackground) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'sent' => $total, 'inserted' => $inserted, 'updated' => $updated, 'auditoria_sent' => $auditoriaSent, 'auditoria_inserted' => $auditoriaInserted]);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exportar a la web</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <a href="index.php" class="btn btn-primary">Volver al inicio</a>
    </div>
</body>
</html>
