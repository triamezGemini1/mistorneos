<?php
/**
 * Asigna UUID a los usuarios en MySQL que no lo tienen.
 * Así el endpoint fetch_jugadores.php (que solo devuelve usuarios con uuid) podrá incluirlos.
 *
 * Ejecutar en el servidor (o donde esté la base MySQL):
 *   php scripts/asignar_uuid_usuarios.php
 */
declare(strict_types=1);

$base = dirname(__DIR__);
require_once $base . '/config/bootstrap.php';
require_once $base . '/config/db.php';

try {
    $pdo = DB::pdo();

    // Comprobar si existe la columna uuid
    $stmt = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'uuid'");
    if ($stmt->fetch() === false) {
        echo "La tabla usuarios no tiene columna 'uuid'. Ejecuta antes la migración que la añade." . PHP_EOL;
        exit(1);
    }

    $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE uuid IS NULL OR uuid = ''");
    $sinUuid = (int) $stmt->fetchColumn();
    if ($sinUuid === 0) {
        echo "Todos los usuarios tienen ya UUID. Nada que hacer." . PHP_EOL;
        exit(0);
    }

    // MySQL: UUID() genera un valor distinto por fila
    $updated = $pdo->exec("UPDATE usuarios SET uuid = UUID() WHERE uuid IS NULL OR uuid = ''");

    echo "Se asignó UUID a " . ($updated ?: 0) . " usuario(s). Ya puedes ejecutar import_from_web.php en el desktop." . PHP_EOL;
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
