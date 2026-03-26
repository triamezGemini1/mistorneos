<?php
/**
 * Script para permitir que numero_cuenta y tipo_cuenta sean NULL
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

try {
    $pdo = DB::pdo();
    
    echo "Modificando tabla cuentas_bancarias...\n";
    
    // Modificar numero_cuenta para permitir NULL
    $pdo->exec("ALTER TABLE cuentas_bancarias MODIFY COLUMN numero_cuenta VARCHAR(50) NULL COMMENT 'Número de cuenta'");
    echo "✓ numero_cuenta ahora permite NULL\n";
    
    // Modificar tipo_cuenta para permitir NULL
    $pdo->exec("ALTER TABLE cuentas_bancarias MODIFY COLUMN tipo_cuenta ENUM('corriente', 'ahorro', 'pagomovil') NULL COMMENT 'Tipo de cuenta'");
    echo "✓ tipo_cuenta ahora permite NULL\n";
    
    echo "\n¡Tabla actualizada correctamente!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

