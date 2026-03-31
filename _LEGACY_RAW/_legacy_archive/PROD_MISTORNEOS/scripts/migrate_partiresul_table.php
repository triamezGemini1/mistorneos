<?php
/**
 * Script de migración para crear tabla partiresul
 * 
 * Este script crea la tabla partiresul para llevar control de las partidas
 * realizadas en cada torneo.
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

echo "=== MIGRACIÓN: Creación de tabla partiresul ===\n\n";

try {
    $pdo = DB::pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Leer el archivo SQL
    $sql_file = __DIR__ . '/../sql/migrate_partiresul_table.sql';
    
    if (!file_exists($sql_file)) {
        throw new Exception("Archivo SQL no encontrado: $sql_file");
    }
    
    $sql = file_get_contents($sql_file);
    
    // Dividir en statements individuales
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            $stmt_clean = trim($stmt);
            return !empty($stmt_clean) && 
                   !preg_match('/^--/', $stmt_clean) && 
                   !preg_match('/^USE /i', $stmt_clean) &&
                   !preg_match('/^\/\*/', $stmt_clean) &&
                   !preg_match('/^NOTA:/i', $stmt_clean) &&
                   !preg_match('/^NOTAS IMPORTANTES/i', $stmt_clean) &&
                   !preg_match('/^IMPORTANTE:/i', $stmt_clean);
        }
    );
    
    echo "Ejecutando migración...\n\n";
    
    foreach ($statements as $index => $statement) {
        if (empty(trim($statement))) {
            continue;
        }
        
        // Saltar comentarios y secciones de notas
        if (preg_match('/^--|^\/\*|\*\/$|NOTAS IMPORTANTES/i', $statement)) {
            continue;
        }
        
        try {
            echo "Ejecutando statement " . ($index + 1) . "...\n";
            $pdo->exec($statement);
            echo "✓ Statement " . ($index + 1) . " ejecutado correctamente\n\n";
        } catch (PDOException $e) {
            // Si es un error de tabla ya existe o constraint ya existe, continuar
            if (strpos($e->getMessage(), 'already exists') !== false || 
                strpos($e->getMessage(), 'Duplicate key name') !== false ||
                strpos($e->getMessage(), 'Duplicate foreign key') !== false) {
                echo "⚠ Statement " . ($index + 1) . ": " . $e->getMessage() . "\n";
                echo "  (Continuando...)\n\n";
                continue;
            }
            throw $e;
        }
    }
    
    // Verificar que la tabla se creó correctamente
    echo "Verificando estructura de la tabla partiresul...\n";
    $stmt = $pdo->query("DESCRIBE partiresul");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($columns)) {
        throw new Exception("La tabla partiresul no se creó correctamente");
    }
    
    echo "✓ Tabla partiresul creada correctamente con " . count($columns) . " columnas\n\n";
    
    // Mostrar estructura
    echo "Estructura de la tabla:\n";
    foreach ($columns as $column) {
        $null = $column['Null'] === 'YES' ? 'NULL' : 'NOT NULL';
        $default = $column['Default'] !== null ? " DEFAULT '{$column['Default']}'" : '';
        echo "  - {$column['Field']} ({$column['Type']}) {$null}{$default}\n";
    }
    
    // Verificar índices
    echo "\nVerificando índices...\n";
    $stmt = $pdo->query("SHOW INDEX FROM partiresul");
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $index_names = array_unique(array_column($indexes, 'Key_name'));
    echo "✓ Índices encontrados: " . count($index_names) . "\n";
    foreach ($index_names as $idx_name) {
        echo "  - {$idx_name}\n";
    }
    
    // Verificar foreign keys
    echo "\nVerificando foreign keys...\n";
    $stmt = $pdo->query("
        SELECT 
            CONSTRAINT_NAME,
            TABLE_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'partiresul'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $fks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($fks)) {
        echo "✓ Foreign keys encontradas: " . count($fks) . "\n";
        foreach ($fks as $fk) {
            echo "  - {$fk['CONSTRAINT_NAME']}: {$fk['COLUMN_NAME']} → {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}\n";
        }
    } else {
        echo "⚠ No se encontraron foreign keys (puede ser normal si las tablas referenciadas no existen aún)\n";
    }
    
    echo "\n=== MIGRACIÓN COMPLETADA ===\n";
    echo "\nLa tabla partiresul está lista para almacenar resultados de partidas.\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

