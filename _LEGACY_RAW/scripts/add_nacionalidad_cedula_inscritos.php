<?php
/**
 * Migración: añadir nacionalidad y cedula a tabla inscritos.
 * Permite búsqueda directa por (torneo_id, nacionalidad, cedula) en NIVEL 1.
 *
 * Uso: php scripts/add_nacionalidad_cedula_inscritos.php
 */
if (php_sapi_name() !== 'cli') {
    die('Solo ejecución por consola.');
}

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

$pdo = DB::pdo();

echo "=== Migración: nacionalidad y cedula en inscritos ===\n\n";

$cols = $pdo->query("SHOW COLUMNS FROM inscritos")->fetchAll(PDO::FETCH_COLUMN);
$hasNac = in_array('nacionalidad', $cols, true);
$hasCed = in_array('cedula', $cols, true);

if ($hasNac && $hasCed) {
    echo "Las columnas nacionalidad y cedula ya existen.\n";
    $pdo->exec("UPDATE inscritos i INNER JOIN usuarios u ON u.id = i.id_usuario SET i.nacionalidad = COALESCE(NULLIF(TRIM(u.nacionalidad), ''), 'V'), i.cedula = COALESCE(NULLIF(TRIM(u.cedula), ''), '') WHERE i.cedula = '' OR i.cedula IS NULL");
    echo "Backfill de filas existentes ejecutado.\n";
    exit(0);
}

if (!$hasNac) {
    $pdo->exec("ALTER TABLE inscritos ADD COLUMN nacionalidad CHAR(1) NOT NULL DEFAULT 'V' COMMENT 'V, E, J, P'");
    echo "Columna nacionalidad añadida.\n";
}
if (!$hasCed) {
    $pdo->exec("ALTER TABLE inscritos ADD COLUMN cedula VARCHAR(20) NOT NULL DEFAULT '' COMMENT 'Cédula del inscrito'");
    echo "Columna cedula añadida.\n";
}

$pdo->exec("UPDATE inscritos i INNER JOIN usuarios u ON u.id = i.id_usuario SET i.nacionalidad = COALESCE(NULLIF(TRIM(u.nacionalidad), ''), 'V'), i.cedula = COALESCE(NULLIF(TRIM(u.cedula), ''), '') WHERE i.cedula = '' OR (i.nacionalidad = 'V' AND i.cedula = '')");
echo "Backfill desde usuarios ejecutado.\n";

try {
    $pdo->exec("ALTER TABLE inscritos ADD INDEX idx_inscritos_torneo_nac_cedula (torneo_id, nacionalidad, cedula)");
    echo "Índice idx_inscritos_torneo_nac_cedula creado.\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate key') !== false) {
        echo "Índice ya existe.\n";
    } else {
        throw $e;
    }
}

echo "\nMigración completada.\n";
