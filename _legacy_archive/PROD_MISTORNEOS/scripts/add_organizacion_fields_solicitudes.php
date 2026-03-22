<?php
/**
 * Añade a solicitudes_afiliacion columnas de organización:
 * org_direccion, org_responsable, org_telefono, org_email
 * para alinear con la estructura de la tabla organizaciones.
 */
$base = dirname(__DIR__);
require_once $base . '/config/bootstrap.php';
require_once $base . '/config/db.php';

$pdo = DB::pdo();
$cols = $pdo->query("SHOW COLUMNS FROM solicitudes_afiliacion")->fetchAll(PDO::FETCH_ASSOC);
$existing = array_map(function ($c) {
    return strtolower($c['Field'] ?? $c['field'] ?? '');
}, $cols);

$add = [
    'org_direccion'   => "ALTER TABLE solicitudes_afiliacion ADD COLUMN org_direccion VARCHAR(255) NULL AFTER club_ubicacion",
    'org_responsable' => "ALTER TABLE solicitudes_afiliacion ADD COLUMN org_responsable VARCHAR(100) NULL AFTER org_direccion",
    'org_telefono'    => "ALTER TABLE solicitudes_afiliacion ADD COLUMN org_telefono VARCHAR(50) NULL AFTER org_responsable",
    'org_email'       => "ALTER TABLE solicitudes_afiliacion ADD COLUMN org_email VARCHAR(100) NULL AFTER org_telefono",
];

foreach ($add as $field => $sql) {
    if (in_array($field, $existing, true)) {
        echo "Columna {$field} ya existe.\n";
        continue;
    }
    try {
        $pdo->exec($sql);
        echo "Columna {$field} añadida correctamente.\n";
    } catch (Exception $e) {
        echo "Error añadiendo {$field}: " . $e->getMessage() . "\n";
    }
}

echo "Listo.\n";
