<?php
/**
 * Script para agregar la columna es_evento_masivo a la tabla tournaments
 * Ejecutar desde la línea de comandos: php scripts/add_evento_masivo_column.php
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

echo "========================================\n";
echo "Agregando columna es_evento_masivo\n";
echo "========================================\n\n";

try {
    $pdo = DB::pdo();
    
    // Verificar si la columna ya existe
    $stmt = $pdo->query("SHOW COLUMNS FROM tournaments LIKE 'es_evento_masivo'");
    if ($stmt->rowCount() > 0) {
        echo "✓ La columna 'es_evento_masivo' ya existe en la tabla tournaments\n";
    } else {
        // Agregar la columna
        echo "Agregando columna es_evento_masivo...\n";
        $pdo->exec("
            ALTER TABLE tournaments 
            ADD COLUMN es_evento_masivo TINYINT(1) NOT NULL DEFAULT 0 
            COMMENT '1 = Evento masivo con inscripción pública, 0 = Torneo normal' 
            AFTER estatus
        ");
        echo "✓ Columna 'es_evento_masivo' agregada exitosamente\n";
    }
    
    // Verificar si el índice ya existe
    $stmt = $pdo->query("SHOW INDEX FROM tournaments WHERE Key_name = 'idx_es_evento_masivo'");
    if ($stmt->rowCount() > 0) {
        echo "✓ El índice 'idx_es_evento_masivo' ya existe\n";
    } else {
        // Crear el índice
        echo "Creando índice idx_es_evento_masivo...\n";
        $pdo->exec("
            CREATE INDEX idx_es_evento_masivo ON tournaments(es_evento_masivo, fechator)
        ");
        echo "✓ Índice 'idx_es_evento_masivo' creado exitosamente\n";
    }
    
    echo "\n========================================\n";
    echo "✓ Proceso completado exitosamente\n";
    echo "========================================\n";
    
} catch (PDOException $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "Código: " . $e->getCode() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

