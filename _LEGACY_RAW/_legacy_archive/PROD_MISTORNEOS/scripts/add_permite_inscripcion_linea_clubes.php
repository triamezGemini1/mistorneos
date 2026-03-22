<?php
/**
 * Script para agregar la columna permite_inscripcion_linea a la tabla clubes
 * Ejecutar: php scripts/add_permite_inscripcion_linea_clubes.php
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

try {
    $pdo = DB::pdo();
    
    $stmt = $pdo->query("SHOW COLUMNS FROM clubes LIKE 'permite_inscripcion_linea'");
    if ($stmt->rowCount() > 0) {
        echo "La columna permite_inscripcion_linea ya existe en clubes.\n";
        exit(0);
    }
    
    $pdo->exec("
        ALTER TABLE clubes 
        ADD COLUMN permite_inscripcion_linea TINYINT(1) NOT NULL DEFAULT 1 
        COMMENT '1=permite inscripción en línea a afiliados, 0=solo en sitio' 
        AFTER estatus
    ");
    
    echo "Columna permite_inscripcion_linea agregada correctamente a clubes.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
