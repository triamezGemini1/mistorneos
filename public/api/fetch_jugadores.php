<?php
/**
 * Endpoint de sincronización: devuelve jugadores (usuarios) para la app desktop.
 * Solo accesible con API_KEY (header X-API-Key o query api_key).
 * Autocontenido: carga config desde la raíz del proyecto (../../).
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$baseDir = dirname(__DIR__, 2);
if (!is_file($baseDir . '/config/bootstrap.php')) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Not Found', 'jugadores' => []]);
    exit;
}
require_once $baseDir . '/config/bootstrap.php';
require_once $baseDir . '/config/db.php';

$apiKey = trim((string)($_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? ''));
error_log('[fetch_jugadores] API key recibida (longitud ' . strlen($apiKey) . '): ' . (strlen($apiKey) > 0 ? substr($apiKey, 0, 4) . '...' : '(vacía)'));

$expectedKey = class_exists('Env') ? (Env::get('SYNC_API_KEY') ?: Env::get('API_KEY')) : '';
$expectedKey = trim((string)$expectedKey);
$hardcodedKey = 'TorneoMaster2024*';
$valid = $apiKey !== '' && (
    ($expectedKey !== '' && hash_equals($expectedKey, $apiKey)) ||
    hash_equals($hardcodedKey, $apiKey)
);

if (!$valid) {
    http_response_code(401);
    $msg = $expectedKey === '' && $apiKey !== $hardcodedKey
        ? 'No autorizado. En el servidor, configura SYNC_API_KEY en el archivo .env (mismo valor que en desktop/config_sync.php).'
        : 'No autorizado. Proporciona X-API-Key o api_key en la URL y que coincida con SYNC_API_KEY del .env del servidor.';
    echo json_encode([
        'ok'        => false,
        'message'   => $msg,
        'jugadores' => [],
    ]);
    exit;
}

try {
    $pdo = DB::pdo();

    // Opcional: asignar UUID en la base remota a usuarios que no lo tienen (solo si ?asignar_uuid=1)
    $uuidAsignados = 0;
    $asignarUuid = isset($_GET['asignar_uuid']) && $_GET['asignar_uuid'] === '1';
    if ($asignarUuid) {
        try {
            if ($pdo->query("SHOW COLUMNS FROM usuarios LIKE 'uuid'")->fetch()) {
                $uuidAsignados = $pdo->exec("UPDATE usuarios SET uuid = UUID() WHERE uuid IS NULL OR uuid = ''") ?: 0;
            }
        } catch (Throwable $e) {
        }
    }

    $columns = ['id', 'uuid', 'nombre', 'cedula', 'nacionalidad', 'sexo', 'fechnac', 'email', 'username', 'club_id', 'entidad', 'status', 'role'];
    $hasLastUpdated = false;
    $hasSyncStatus = false;
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'last_updated'");
        $hasLastUpdated = $stmt->fetch() !== false;
        $stmt = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'sync_status'");
        $hasSyncStatus = $stmt->fetch() !== false;
    } catch (Throwable $e) {
    }
    if ($hasLastUpdated) {
        $columns[] = 'last_updated';
    }
    if ($hasSyncStatus) {
        $columns[] = 'sync_status';
    }
    $cols = implode(', ', $columns);
    $sql = "SELECT {$cols} FROM usuarios WHERE uuid IS NOT NULL AND uuid != '' ORDER BY id";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        if (isset($row['fechnac']) && $row['fechnac'] !== null) {
            $row['fechnac'] = (string)$row['fechnac'];
        }
        if (isset($row['last_updated']) && $row['last_updated'] !== null) {
            $row['last_updated'] = (string)$row['last_updated'];
        }
    }
    unset($row);

    $payload = ['ok' => true, 'jugadores' => $rows, 'total' => count($rows)];
    if ($uuidAsignados > 0) {
        $payload['uuid_asignados'] = $uuidAsignados;
        $payload['message'] = "Se asignó UUID a {$uuidAsignados} usuario(s) en la base remota.";
    }
    echo json_encode($payload);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'        => false,
        'message'   => 'Error al obtener jugadores.',
        'jugadores' => [],
        'error'     => $e->getMessage(),
    ]);
}
