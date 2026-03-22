<?php
/**
 * Script para agregar la columna email a la tabla clubes
 * Ejecutar: php scripts/add_email_to_clubes.php
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

try {
    $pdo = DB::pdo();
    
    // Verificar si la columna ya existe
    $stmt = $pdo->query("SHOW COLUMNS FROM clubes LIKE 'email'");
    if ($stmt->rowCount() > 0) {
        echo "La columna 'email' ya existe en la tabla clubes.\n";
        exit(0);
    }
    
    // Agregar la columna
    $pdo->exec("ALTER TABLE clubes ADD COLUMN email VARCHAR(255) NULL AFTER telefono");
    echo "Columna 'email' agregada exitosamente a la tabla clubes.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
