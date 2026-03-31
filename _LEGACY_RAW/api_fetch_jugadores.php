<?php
/**
 * Endpoint de sincronización: devuelve jugadores (usuarios) con uuid y last_updated.
 * Solo accesible con API_KEY (header X-API-Key o query api_key).
 * Uso: para que la app de escritorio haga pull inicial o sincronización.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/config/db.php';

// Validación de token API_KEY
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
$expectedKey = class_exists('Env') ? (Env::get('SYNC_API_KEY') ?: Env::get('API_KEY')) : '';

if ($expectedKey === '' || $apiKey === '' || !hash_equals((string)$expectedKey, (string)$apiKey)) {
    http_response_code(401);
    echo json_encode([
        'ok'      => false,
        'message' => 'No autorizado. Proporciona X-API-Key en cabecera o api_key en query.',
        'jugadores' => [],
    ]);
    exit;
}

try {
    $pdo = DB::pdo();

    // Columnas que pueden no existir en instalaciones antiguas (last_updated, sync_status)
    $columns = ['id', 'uuid', 'nombre', 'cedula', 'nacionalidad', 'sexo', 'fechnac', 'email', 'username', 'club_id', 'entidad', 'status', 'role'];
    $hasLastUpdated = false;
    $hasSyncStatus = false;
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'last_updated'");
        $hasLastUpdated = $stmt->fetch() !== false;
        $stmt = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'sync_status'");
        $hasSyncStatus = $stmt->fetch() !== false;
    } catch (Throwable $e) {
        // ignorar
    }
    if ($hasLastUpdated) {
        $columns[] = 'last_updated';
    }
    if ($hasSyncStatus) {
        $columns[] = 'sync_status';
    }

    $cols = implode(', ', $columns);
    $sql = "SELECT {$cols} FROM usuarios WHERE uuid IS NOT NULL AND uuid != '' ORDER BY id";
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Normalizar fechas a string para JSON
    foreach ($rows as &$row) {
        if (isset($row['fechnac']) && $row['fechnac'] !== null) {
            $row['fechnac'] = (string)$row['fechnac'];
        }
        if (isset($row['last_updated']) && $row['last_updated'] !== null) {
            $row['last_updated'] = (string)$row['last_updated'];
        }
    }
    unset($row);

    echo json_encode([
        'ok'        => true,
        'jugadores' => $rows,
        'total'     => count($rows),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'        => false,
        'message'   => 'Error al obtener jugadores.',
        'jugadores' => [],
        'error'     => $e->getMessage(),
    ]);
}
