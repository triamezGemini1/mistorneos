<?php
/**
 * Añade user_id a solicitudes_afiliacion para vincular solicitud con usuario
 * (creado al solicitar si no existía, o usuario ya registrado).
 */
$base = dirname(__DIR__);
require_once $base . '/config/bootstrap.php';
require_once $base . '/config/db.php';

$pdo = DB::pdo();
$cols = $pdo->query("SHOW COLUMNS FROM solicitudes_afiliacion")->fetchAll(PDO::FETCH_ASSOC);
$existing = array_map(function ($c) {
    return strtolower($c['Field'] ?? $c['field'] ?? '');
}, $cols);

if (in_array('user_id', $existing, true)) {
    echo "Columna user_id ya existe.\n";
    exit(0);
}

try {
    $pdo->exec("ALTER TABLE solicitudes_afiliacion ADD COLUMN user_id INT NULL AFTER id");
    echo "Columna user_id añadida correctamente.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
