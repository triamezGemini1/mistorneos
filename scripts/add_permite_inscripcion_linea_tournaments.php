<?php
/**
 * Script para agregar la columna permite_inscripcion_linea a la tabla tournaments
 * Ejecutar: php scripts/add_permite_inscripcion_linea_tournaments.php
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

try {
    $pdo = DB::pdo();
    
    $stmt = $pdo->query("SHOW COLUMNS FROM tournaments LIKE 'permite_inscripcion_linea'");
    if ($stmt->rowCount() > 0) {
        echo "La columna permite_inscripcion_linea ya existe en tournaments.\n";
        exit(0);
    }
    
    $pdo->exec("
        ALTER TABLE tournaments 
        ADD COLUMN permite_inscripcion_linea TINYINT(1) NOT NULL DEFAULT 1 
        COMMENT '1=permite inscripción en línea, 0=solo en sitio' 
        AFTER estatus
    ");
    
    echo "Columna permite_inscripcion_linea agregada correctamente a tournaments.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
