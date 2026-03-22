<?php
/**
 * Script para ejecutar la migración: Agregar campo sexo a la tabla users
 * 
 * Uso: php scripts/migrate_add_sexo_to_users.php
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

echo "=== MIGRACIÓN: Agregar campo sexo a la tabla users ===\n\n";

try {
    $pdo = DB::pdo();
    echo "✓ Conectado a la base de datos\n";
    
    // Verificar si la columna ya existe
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'sexo'");
    if ($stmt->fetch()) {
        echo "⚠ La columna 'sexo' ya existe en la tabla users.\n";
        echo "¿Desea continuar de todos modos? (Esto no causará errores si ya existe)\n";
    }
    
    echo "\nEjecutando migración...\n";
    
    // Agregar columna sexo
    $pdo->exec("
        ALTER TABLE users 
        ADD COLUMN IF NOT EXISTS sexo ENUM('M','F','O') NULL DEFAULT NULL 
        AFTER fechnac
    ");
    echo "✓ Columna 'sexo' agregada\n";
    
    // Crear índice (ignorar si ya existe)
    try {
        $pdo->exec("CREATE INDEX idx_users_sexo ON users(sexo)");
        echo "✓ Índice 'idx_users_sexo' creado\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate key') !== false) {
            echo "⚠ Índice 'idx_users_sexo' ya existe\n";
        } else {
            throw $e;
        }
    }
    
    // Agregar comentario
    $pdo->exec("
        ALTER TABLE users MODIFY COLUMN sexo ENUM('M','F','O') NULL DEFAULT NULL 
        COMMENT 'Género del usuario: M=Masculino, F=Femenino, O=Otro'
    ");
    echo "✓ Comentario agregado a la columna\n";
    
    echo "\n✓ Migración completada exitosamente!\n";
    echo "\nPróximo paso: Ejecutar 'php scripts/update_users_sexo_from_persona.php' para actualizar usuarios existentes.\n";
    
} catch (Exception $e) {
    // Si MySQL no soporta IF NOT EXISTS, intentar sin él
    if (strpos($e->getMessage(), 'IF NOT EXISTS') !== false) {
        echo "⚠ MySQL no soporta 'IF NOT EXISTS' en ALTER TABLE. Intentando sin él...\n";
        try {
            $pdo->exec("
                ALTER TABLE users 
                ADD COLUMN sexo ENUM('M','F','O') NULL DEFAULT NULL 
                AFTER fechnac
            ");
            echo "✓ Columna 'sexo' agregada\n";
            
            try {
                $pdo->exec("CREATE INDEX idx_users_sexo ON users(sexo)");
                echo "✓ Índice 'idx_users_sexo' creado\n";
            } catch (Exception $e2) {
                if (strpos($e2->getMessage(), 'Duplicate') !== false) {
                    echo "⚠ Índice 'idx_users_sexo' ya existe\n";
                } else {
                    throw $e2;
                }
            }
            
            $pdo->exec("
                ALTER TABLE users MODIFY COLUMN sexo ENUM('M','F','O') NULL DEFAULT NULL 
                COMMENT 'Género del usuario: M=Masculino, F=Femenino, O=Otro'
            ");
            echo "✓ Comentario agregado a la columna\n";
            echo "\n✓ Migración completada exitosamente!\n";
        } catch (Exception $e3) {
            if (strpos($e3->getMessage(), 'Duplicate column') !== false) {
                echo "⚠ La columna 'sexo' ya existe. Migración ya aplicada.\n";
            } else {
                echo "\n❌ ERROR: " . $e3->getMessage() . "\n";
                exit(1);
            }
        }
    } else {
        echo "\n❌ ERROR: " . $e->getMessage() . "\n";
        exit(1);
    }
}


