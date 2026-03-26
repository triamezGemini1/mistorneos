<?php
/**
 * Script para actualizar campo estatus de ENUM a INT en tabla inscritos
 * 
 * Este script:
 * 1. Convierte valores ENUM existentes a INT
 * 2. Modifica la columna estatus de ENUM a INT
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

echo "=== MIGRACIÓN: Cambiar estatus de ENUM a INT en tabla inscritos ===\n\n";

try {
    $pdo = DB::pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Verificar si la tabla existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'inscripciones'");
    if ($stmt->rowCount() === 0) {
        echo "⚠ La tabla 'inscripciones' no existe aún.\n";
        exit(0);
    }
    
    // Verificar estructura actual de la columna estatus
    echo "Verificando estructura actual de la columna estatus...\n";
    $stmt = $pdo->query("DESCRIBE inscripciones");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $estatus_column = null;
    foreach ($columns as $column) {
        if ($column['Field'] === 'estatus') {
            $estatus_column = $column;
            break;
        }
    }
    
    if (!$estatus_column) {
        throw new Exception("La columna 'estatus' no existe en la tabla 'inscritos'");
    }
    
    echo "Tipo actual: {$estatus_column['Type']}\n";
    echo "Default actual: {$estatus_column['Default']}\n\n";
    
    // Si ya es INT, no hacer nada
    if (strpos(strtolower($estatus_column['Type']), 'int') !== false) {
        echo "✓ La columna 'estatus' ya es de tipo INT. No se requiere migración.\n";
        exit(0);
    }
    
    // Leer el archivo SQL
    $sql_file = __DIR__ . '/../sql/update_inscritos_estatus_to_int.sql';
    
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
        
        // Saltar comentarios
        if (preg_match('/^--|^\/\*|\*\/$|NOTAS IMPORTANTES/i', $statement)) {
            continue;
        }
        
        try {
            echo "Ejecutando statement " . ($index + 1) . "...\n";
            
            // Si es UPDATE, verificar si hay datos primero
            if (preg_match('/^UPDATE/i', $statement)) {
                $check_stmt = $pdo->query("SELECT COUNT(*) FROM inscripciones WHERE estatus IN ('pendiente', 'confirmado', 'solvente', 'no_solvente', 'retirado') OR CAST(estatus AS CHAR) IN ('0', '1', '2', '3', '4')");
                $count = $check_stmt->fetchColumn();
                if ($count > 0) {
                    echo "  Migrando $count registros de ENUM a INT...\n";
                    $pdo->exec($statement);
                    echo "  ✓ Registros migrados correctamente\n";
                } else {
                    echo "  ⚠ No hay registros para migrar\n";
                }
            } else {
                $pdo->exec($statement);
            }
            
            echo "✓ Statement " . ($index + 1) . " ejecutado correctamente\n\n";
        } catch (PDOException $e) {
            // Si es un error de tabla ya existe, continuar
            if (strpos($e->getMessage(), 'already exists') !== false || 
                strpos($e->getMessage(), 'Duplicate') !== false) {
                echo "⚠ Statement " . ($index + 1) . ": " . $e->getMessage() . "\n";
                echo "  (Continuando...)\n\n";
                continue;
            }
            throw $e;
        }
    }
    
    // Verificar que la columna se modificó correctamente
    echo "Verificando nueva estructura de la columna estatus...\n";
    $stmt = $pdo->query("DESCRIBE inscripciones");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        if ($column['Field'] === 'estatus') {
            echo "✓ Columna 'estatus' actualizada:\n";
            echo "  - Tipo: {$column['Type']}\n";
            echo "  - Default: {$column['Default']}\n";
            echo "  - Null: {$column['Null']}\n";
            break;
        }
    }
    
    // Verificar algunos registros de ejemplo
    echo "\nVerificando valores en registros existentes...\n";
    $stmt = $pdo->query("SELECT DISTINCT estatus, COUNT(*) as total FROM inscripciones GROUP BY estatus ORDER BY estatus");
    $estatus_values = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($estatus_values)) {
        echo "Valores encontrados:\n";
        foreach ($estatus_values as $row) {
            $estatus_num = (int)$row['estatus'];
            $estatus_texto = [
                0 => 'pendiente',
                1 => 'confirmado',
                2 => 'solvente',
                3 => 'no_solvente',
                4 => 'retirado'
            ][$estatus_num] ?? 'desconocido';
            echo "  - Estatus {$estatus_num} ({$estatus_texto}): {$row['total']} registros\n";
        }
    } else {
        echo "  ⚠ No hay registros en la tabla\n";
    }
    
    echo "\n=== MIGRACIÓN COMPLETADA ===\n";
    echo "\nPRÓXIMOS PASOS:\n";
    echo "1. Usar InscritosHelper::getEstatusTexto() para mostrar texto en formularios\n";
    echo "2. Usar InscritosHelper::getEstatusNumero() para guardar valores numéricos\n";
    echo "3. Actualizar formularios para usar valores numéricos en los campos\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

