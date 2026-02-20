<?php
/**
 * Test de bootstrap completo
 * ELIMINAR DESPUÉS DE USAR
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<h1>Test de Bootstrap Completo</h1>";
echo "<pre>";

try {
    echo "=== Cargando bootstrap.php ===\n";
    require_once __DIR__ . '/../config/bootstrap.php';
    echo "✅ bootstrap.php cargado\n\n";
    
    echo "=== Verificando clases ===\n";
    if (class_exists('Log')) {
        echo "✅ Clase Log existe\n";
        
        if (method_exists('Log', 'info')) {
            echo "✅ Log::info() existe\n";
        }
        
        if (method_exists('Log', 'logMessage')) {
            echo "✅ Log::logMessage() existe\n";
        }
        
        if (method_exists('Log', 'log')) {
            echo "❌ PROBLEMA: Log::log() todavía existe\n";
        } else {
            echo "✅ Log::log() NO existe (correcto)\n";
        }
    } else {
        echo "❌ Clase Log NO existe\n";
    }
    
    echo "\n=== Verificando DB ===\n";
    if (class_exists('DB')) {
        echo "✅ Clase DB existe\n";
        try {
            $pdo = DB::pdo();
            echo "✅ Conexión a BD exitosa\n";
        } catch (Exception $e) {
            echo "❌ Error de conexión: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n=== Verificando Auth ===\n";
    if (class_exists('Auth')) {
        echo "✅ Clase Auth existe\n";
    }
    
    echo "\n✅ TODO CORRECTO - Bootstrap funciona\n";
    
} catch (Throwable $e) {
    echo "❌ ERROR FATAL:\n";
    echo "   Tipo: " . get_class($e) . "\n";
    echo "   Mensaje: " . $e->getMessage() . "\n";
    echo "   Archivo: " . $e->getFile() . "\n";
    echo "   Línea: " . $e->getLine() . "\n";
    echo "\n   Stack Trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "</pre>";
echo "<p><strong>⚠️ RECUERDA ELIMINAR ESTE ARCHIVO (test_bootstrap.php) DESPUÉS DE USAR</strong></p>";












