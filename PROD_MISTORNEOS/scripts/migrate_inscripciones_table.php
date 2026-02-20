<?php
/**
 * Script de migración para reestructurar tabla inscripciones
 * 
 * Este script:
 * 1. Agrega nuevos campos a la tabla inscripciones existente
 * 2. Convierte el campo estatus a INT
 * 3. Agrega índices necesarios
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

echo "=== MIGRACIÓN: Reestructuración de tabla inscripciones ===\n\n";

try {
    $pdo = DB::pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Verificar si la tabla existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'inscripciones'");
    if ($stmt->rowCount() === 0) {
        throw new Exception("La tabla 'inscripciones' no existe. Debe crearse primero.");
    }
    
    echo "✓ Tabla 'inscripciones' encontrada\n\n";
    
    // Verificar estructura actual
    echo "Estructura actual de la tabla:\n";
    $stmt = $pdo->query("DESCRIBE inscripciones");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $existing_columns = array_column($columns, 'Field');
    echo "Columnas existentes: " . implode(', ', $existing_columns) . "\n\n";
    
    // Leer el archivo SQL
    $sql_file = __DIR__ . '/../sql/migrate_inscripciones_table.sql';
    
    if (!file_exists($sql_file)) {
        throw new Exception("Archivo SQL no encontrado: $sql_file");
    }
    
    $sql = file_get_contents($sql_file);
    
    // Función helper para verificar si una columna existe
    $columnExists = function($column_name) use ($pdo, $existing_columns) {
        return in_array($column_name, $existing_columns);
    };
    
    // Función helper para verificar si un índice existe
    $indexExists = function($index_name) use ($pdo) {
        try {
            $stmt = $pdo->query("SHOW INDEX FROM inscripciones WHERE Key_name = '$index_name'");
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    };
    
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
                   !preg_match('/^IMPORTANTE:/i', $stmt_clean) &&
                   !preg_match('/^CREATE TABLE/i', $stmt_clean) &&
                   !preg_match('/^SELECT \*/i', $stmt_clean);
        }
    );
    
    echo "Ejecutando migración...\n\n";
    
    $executed = 0;
    $skipped = 0;
    $errors = 0;
    
    foreach ($statements as $index => $statement) {
        if (empty(trim($statement))) {
            continue;
        }
        
        // Saltar comentarios
        if (preg_match('/^--|^\/\*|\*\/$|NOTAS IMPORTANTES/i', $statement)) {
            continue;
        }
        
        // Verificar si es ADD COLUMN y si la columna ya existe
        if (preg_match('/ALTER TABLE.*ADD COLUMN.*`?(\w+)`?/i', $statement, $matches)) {
            $column_name = $matches[1] ?? null;
            if ($column_name && $columnExists($column_name)) {
                echo "⚠ Columna '$column_name' ya existe, omitiendo...\n\n";
                $skipped++;
                continue;
            }
        }
        
        // Verificar si es ADD INDEX y si el índice ya existe
        if (preg_match('/ADD (?:UNIQUE )?INDEX.*`?(\w+)`?/i', $statement, $matches)) {
            $index_name = $matches[1] ?? null;
            if ($index_name && $indexExists($index_name)) {
                echo "⚠ Índice '$index_name' ya existe, omitiendo...\n\n";
                $skipped++;
                continue;
            }
        }
        
        // Remover IF NOT EXISTS (ya verificamos manualmente)
        $statement = preg_replace('/IF NOT EXISTS/i', '', $statement);
        
        try {
            echo "Ejecutando statement " . ($index + 1) . "...\n";
            $pdo->exec($statement);
            echo "✓ Statement " . ($index + 1) . " ejecutado correctamente\n\n";
            $executed++;
            
            // Actualizar lista de columnas existentes después de agregar una
            if (preg_match('/ADD COLUMN.*`?(\w+)`?/i', $statement, $matches)) {
                $new_column = $matches[1] ?? null;
                if ($new_column && !in_array($new_column, $existing_columns)) {
                    $existing_columns[] = $new_column;
                }
            }
        } catch (PDOException $e) {
            // Si es un error de columna/índice ya existe, continuar
            if (strpos($e->getMessage(), 'Duplicate column name') !== false || 
                strpos($e->getMessage(), 'Duplicate key name') !== false ||
                strpos($e->getMessage(), 'already exists') !== false ||
                strpos($e->getMessage(), 'Duplicate entry') !== false) {
                echo "⚠ Statement " . ($index + 1) . ": " . $e->getMessage() . "\n";
                echo "  (Columna/índice ya existe, continuando...)\n\n";
                $skipped++;
                continue;
            }
            
            // Si es error de foreign key (tabla referenciada no existe), continuar
            if (strpos($e->getMessage(), 'Cannot add foreign key constraint') !== false ||
                strpos($e->getMessage(), 'cannot be resolved') !== false) {
                echo "⚠ Statement " . ($index + 1) . ": " . $e->getMessage() . "\n";
                echo "  (Tabla referenciada no existe aún, continuando...)\n\n";
                $skipped++;
                continue;
            }
            
            // Otros errores
            echo "❌ Statement " . ($index + 1) . ": " . $e->getMessage() . "\n";
            $errors++;
        }
    }
    
    echo "\n=== RESUMEN ===\n";
    echo "Ejecutados: $executed\n";
    echo "Omitidos (ya existían): $skipped\n";
    if ($errors > 0) {
        echo "Errores: $errors\n";
    }
    
    // Verificar estructura final
    echo "\nVerificando estructura final de la tabla inscripciones...\n";
    $stmt = $pdo->query("DESCRIBE inscripciones");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "✓ Tabla inscripciones tiene " . count($columns) . " columnas\n\n";
    
    // Mostrar nuevas columnas agregadas
    $new_columns = ['posicion', 'ganados', 'perdidos', 'efectividad', 'puntos', 'ptosrnk', 
                    'sancion', 'chancletas', 'zapatos', 'tarjeta', 'fecha_inscripcion', 
                    'inscrito_por', 'notas'];
    
    echo "Verificando nuevas columnas:\n";
    $current_columns = array_column($columns, 'Field');
    foreach ($new_columns as $col) {
        if (in_array($col, $current_columns)) {
            echo "  ✓ $col\n";
        } else {
            echo "  ⚠ $col (no encontrada)\n";
        }
    }
    
    // Verificar estatus
    echo "\nVerificando campo estatus...\n";
    foreach ($columns as $column) {
        if ($column['Field'] === 'estatus') {
            echo "  - Tipo: {$column['Type']}\n";
            echo "  - Default: {$column['Default']}\n";
            if (strpos(strtolower($column['Type']), 'int') !== false) {
                echo "  ✓ Estatus es de tipo INT\n";
            } else {
                echo "  ⚠ Estatus aún no es INT: {$column['Type']}\n";
            }
            break;
        }
    }
    
    // Verificar índices
    echo "\nVerificando índices...\n";
    $stmt = $pdo->query("SHOW INDEX FROM inscripciones");
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $index_names = array_unique(array_column($indexes, 'Key_name'));
    echo "✓ Índices encontrados: " . count($index_names) . "\n";
    foreach (['idx_posicion', 'idx_puntos', 'idx_ptosrnk', 'idx_estatus'] as $idx) {
        if (in_array($idx, $index_names)) {
            echo "  ✓ $idx\n";
        } else {
            echo "  ⚠ $idx (no encontrado)\n";
        }
    }
    
    echo "\n=== MIGRACIÓN COMPLETADA ===\n";
    echo "\nLa tabla inscripciones ha sido actualizada con los nuevos campos.\n";
    echo "Usa InscritosHelper para manejar el campo estatus (INT).\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

