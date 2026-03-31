<?php
/**
 * Script para crear la tabla tournament_photos
 * Ejecutar una vez para crear la tabla en la base de datos
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

echo "=== Creando tabla tournament_photos ===\n\n";

try {
    $pdo = DB::pdo();
    
    // Leer el archivo SQL
    $sql = file_get_contents(__DIR__ . '/create_tournament_photos_table.sql');
    
    // Ejecutar el SQL
    $pdo->exec($sql);
    
    echo "✓ Tabla tournament_photos creada exitosamente\n\n";
    
    // Verificar que se creó correctamente
    $stmt = $pdo->query("DESCRIBE tournament_photos");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Estructura de la tabla:\n";
    echo str_repeat("-", 60) . "\n";
    foreach ($columns as $column) {
        echo sprintf("%-20s %-20s %s\n", 
            $column['Field'], 
            $column['Type'], 
            $column['Null'] === 'YES' ? 'NULL' : 'NOT NULL'
        );
    }
    echo str_repeat("-", 60) . "\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}






