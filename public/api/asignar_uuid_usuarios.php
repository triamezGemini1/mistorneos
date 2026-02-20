<?php
/**
 * Asigna UUID en la base remota (MySQL del servidor) a usuarios que no lo tienen.
 * Una sola ejecución: abre en el navegador con tu API key y se actualiza la BD.
 * Misma autenticación que fetch_jugadores.php (SYNC_API_KEY o API_KEY).
 *
 * Uso (una vez en producción):
 *   https://tudominio.com/api/asignar_uuid_usuarios.php?api_key=TU_SYNC_API_KEY
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$baseDir = dirname(__DIR__, 2);
if (!is_file($baseDir . '/config/bootstrap.php')) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Not Found']);
    exit;
}
require_once $baseDir . '/config/bootstrap.php';
require_once $baseDir . '/config/db.php';

$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
$expectedKey = class_exists('Env') ? (Env::get('SYNC_API_KEY') ?: Env::get('API_KEY')) : '';

if ($expectedKey === '' || $apiKey === '' || !hash_equals((string)$expectedKey, (string)$apiKey)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'No autorizado. Proporciona api_key en la URL o X-API-Key en cabecera.']);
    exit;
}

try {
    $pdo = DB::pdo();

    $stmt = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'uuid'");
    if ($stmt->fetch() === false) {
        echo json_encode([
            'ok'      => false,
            'message' => 'La tabla usuarios no tiene columna uuid. Ejecuta antes la migración que la añade.',
        ]);
        exit;
    }

    $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE uuid IS NULL OR uuid = ''");
    $sinUuid = (int) $stmt->fetchColumn();
    if ($sinUuid === 0) {
        echo json_encode([
            'ok'     => true,
            'updated' => 0,
            'message' => 'Todos los usuarios tenían ya UUID. Nada que hacer.',
        ]);
        exit;
    }

    $updated = $pdo->exec("UPDATE usuarios SET uuid = UUID() WHERE uuid IS NULL OR uuid = ''");

    echo json_encode([
        'ok'      => true,
        'updated' => (int) $updated,
        'message' => 'Se asignó UUID a ' . $updated . ' usuario(s) en la base remota. Ya puedes ejecutar import_from_web.php en el desktop.',
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'message' => 'Error al asignar UUID.',
        'error'   => $e->getMessage(),
    ]);
}
