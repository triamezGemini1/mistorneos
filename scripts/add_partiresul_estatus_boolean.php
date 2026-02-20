<?php
/**
 * Migración: partiresul.estatus a TINYINT(1)
 * 0 = pendiente, 1 = confirmado
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

$pdo = DB::pdo();
$cols = $pdo->query("SHOW COLUMNS FROM partiresul")->fetchAll(PDO::FETCH_ASSOC);
$estatus_col = null;
foreach ($cols as $c) {
    if ($c['Field'] === 'estatus') {
        $estatus_col = $c;
        break;
    }
}

if (!$estatus_col) {
    echo "Creando columna estatus TINYINT(1) DEFAULT 0...\n";
    $pdo->exec("ALTER TABLE partiresul ADD COLUMN estatus TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=pendiente, 1=confirmado'");
    echo "OK: columna estatus creada.\n";
    exit(0);
}

$type = strtolower($estatus_col['Type']);
if (strpos($type, 'tinyint') !== false || strpos($type, 'int') !== false) {
    echo "La columna estatus ya es numérica. No se requiere migración.\n";
    exit(0);
}

echo "Migrando estatus de VARCHAR/ENUM a TINYINT(1)...\n";
$pdo->exec("ALTER TABLE partiresul ADD COLUMN estatus_tmp TINYINT(1) NOT NULL DEFAULT 0");
$pdo->exec("UPDATE partiresul SET estatus_tmp = CASE 
    WHEN estatus IN ('confirmado', '1') THEN 1 
    ELSE 0 
END");
$pdo->exec("ALTER TABLE partiresul DROP COLUMN estatus");
$pdo->exec("ALTER TABLE partiresul CHANGE COLUMN estatus_tmp estatus TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=pendiente, 1=confirmado'");
echo "OK: migración completada.\n";
