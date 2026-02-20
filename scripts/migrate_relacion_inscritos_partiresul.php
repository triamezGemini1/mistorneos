<?php
/**
 * Script para crear procedimientos y triggers de relación inscritos <-> partiresul
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

echo "=== MIGRACIÓN: Procedimientos y Triggers inscritos <-> partiresul ===\n\n";

try {
    $pdo = DB::pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Verificar que ambas tablas existen
    $stmt = $pdo->query("SHOW TABLES LIKE 'inscritos'");
    if ($stmt->rowCount() === 0) {
        throw new Exception("La tabla 'inscritos' no existe. Ejecuta primero: php scripts/migrate_inscritos_table_final.php");
    }
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'partiresul'");
    if ($stmt->rowCount() === 0) {
        throw new Exception("La tabla 'partiresul' no existe. Ejecuta primero: php scripts/migrate_partiresul_table.php");
    }
    
    echo "✓ Ambas tablas existen\n\n";
    
    // Leer el archivo SQL
    $sql_file = __DIR__ . '/../sql/relacion_inscritos_partiresul.sql';
    
    if (!file_exists($sql_file)) {
        throw new Exception("Archivo SQL no encontrado: $sql_file");
    }
    
    $sql = file_get_contents($sql_file);
    
    // Dividir por DELIMITER para manejar procedimientos y triggers
    $parts = preg_split('/DELIMITER\s+(\/\/|;)/i', $sql);
    
    // Ejecutar statements
    $current_delimiter = ';';
    $full_statement = '';
    
    foreach ($parts as $part) {
        $part = trim($part);
        if (empty($part)) continue;
        
        // Si contiene DELIMITER, cambiar el delimitador
        if (preg_match('/DELIMITER\s+(\/\/|;)/i', $part, $matches)) {
            $current_delimiter = $matches[1];
            continue;
        }
        
        // Agregar al statement completo
        $full_statement .= $part . "\n";
        
        // Si termina con el delimitador actual, ejecutar
        if (substr(rtrim($full_statement), -strlen($current_delimiter)) === $current_delimiter) {
            $statement = rtrim($full_statement);
            $statement = rtrim($statement, $current_delimiter);
            $statement = trim($statement);
            
            if (!empty($statement) && !preg_match('/^--|^\/\*/', $statement)) {
                try {
                    echo "Ejecutando statement...\n";
                    $pdo->exec($statement);
                    echo "✓ Statement ejecutado correctamente\n\n";
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'already exists') !== false) {
                        echo "⚠ Ya existe: " . $e->getMessage() . "\n";
                        echo "  (Continuando...)\n\n";
                    } else {
                        throw $e;
                    }
                }
            }
            
            $full_statement = '';
        }
    }
    
    // Verificar procedimientos creados
    echo "Verificando procedimientos almacenados...\n";
    $stmt = $pdo->query("SHOW PROCEDURE STATUS WHERE Db = DATABASE()");
    $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $expected_procedures = ['sp_actualizar_estadisticas_inscrito', 'sp_actualizar_estadisticas_torneo'];
    foreach ($expected_procedures as $proc_name) {
        $found = false;
        foreach ($procedures as $proc) {
            if ($proc['Name'] === $proc_name) {
                echo "  ✓ Procedimiento '{$proc_name}' existe\n";
                $found = true;
                break;
            }
        }
        if (!$found) {
            echo "  ⚠ Procedimiento '{$proc_name}' no encontrado\n";
        }
    }
    
    // Verificar triggers
    echo "\nVerificando triggers...\n";
    $stmt = $pdo->query("SHOW TRIGGERS");
    $triggers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $expected_triggers = ['tr_partiresul_after_insert', 'tr_partiresul_after_update'];
    foreach ($expected_triggers as $trigger_name) {
        $found = false;
        foreach ($triggers as $trigger) {
            if ($trigger['Trigger'] === $trigger_name) {
                echo "  ✓ Trigger '{$trigger_name}' existe\n";
                $found = true;
                break;
            }
        }
        if (!$found) {
            echo "  ⚠ Trigger '{$trigger_name}' no encontrado\n";
        }
    }
    
    // Verificar vista
    echo "\nVerificando vista...\n";
    $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'");
    $views = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $found_view = false;
    foreach ($views as $view) {
        if ($view['Tables_in_' . $pdo->query('SELECT DATABASE()')->fetchColumn()] === 'v_inscritos_estadisticas') {
            echo "  ✓ Vista 'v_inscritos_estadisticas' existe\n";
            $found_view = true;
            break;
        }
    }
    if (!$found_view) {
        echo "  ⚠ Vista 'v_inscritos_estadisticas' no encontrada\n";
    }
    
    echo "\n=== MIGRACIÓN COMPLETADA ===\n";
    echo "\nLos procedimientos, triggers y vista están listos.\n";
    echo "Las estadísticas se actualizarán automáticamente cuando se registren partidas.\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

