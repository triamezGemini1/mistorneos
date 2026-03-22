<?php
/**
 * Script de migración para reestructurar tabla inscritos
 * 
 * Este script:
 * 1. Crea la nueva tabla inscritos con la estructura mejorada
 * 2. Opcionalmente migra datos de inscripciones a inscritos (si es necesario)
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

echo "=== MIGRACIÓN: Reestructuración de tabla inscritos ===\n\n";

try {
    $pdo = DB::pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Leer el archivo SQL
    $sql_file = __DIR__ . '/../sql/migrate_inscritos_table.sql';
    
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
                   !preg_match('/^NOTAS IMPORTANTES/i', $stmt_clean);
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
    echo "Verificando estructura de la tabla inscritos...\n";
    $stmt = $pdo->query("DESCRIBE inscritos");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($columns)) {
        throw new Exception("La tabla inscritos no se creó correctamente");
    }
    
    echo "✓ Tabla inscritos creada correctamente con " . count($columns) . " columnas\n\n";
    
    // Mostrar estructura
    echo "Estructura de la tabla:\n";
    foreach ($columns as $column) {
        echo "  - {$column['Field']} ({$column['Type']})\n";
    }
    
    echo "\n=== MIGRACIÓN COMPLETADA ===\n";
    echo "\nPRÓXIMOS PASOS:\n";
    echo "1. Verificar que la tabla se creó correctamente\n";
    echo "2. Si hay datos en 'inscripciones', crear script de migración de datos\n";
    echo "3. Actualizar código PHP que usa 'inscripciones' para usar 'inscritos'\n";
    echo "4. Probar funcionalidad con la nueva tabla\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

