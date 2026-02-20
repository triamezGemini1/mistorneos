<?php
/**
 * Test temporal - ELIMINAR DESPUÉS DE USAR
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test de Log.php</h1>";
echo "<pre>";

echo "PHP Version: " . phpversion() . "\n\n";

echo "=== Verificando Log.php ===\n";
$log_file = __DIR__ . '/../lib/Log.php';

if (file_exists($log_file)) {
    echo "✅ Archivo existe\n";
    
    // Leer contenido
    $content = file_get_contents($log_file);
    
    // Verificar si tiene método log() estático
    if (preg_match('/public static function log\s*\(/', $content)) {
        echo "❌ PROBLEMA: Método log() estático encontrado\n";
        echo "   Esto causa error en PHP 8.0+\n";
    } else {
        echo "✅ No se encontró método log() estático problemático\n";
    }
    
    // Verificar si tiene logMessage()
    if (preg_match('/public static function logMessage\s*\(/', $content)) {
        echo "✅ Método logMessage() encontrado (correcto)\n";
    }
    
    echo "\n=== Intentando cargar Log.php ===\n";
    try {
        // Intentar cargar sin dependencias primero
        require_once $log_file;
        echo "✅ Log.php cargado\n";
        
        // Verificar si la clase existe
        if (class_exists('Log')) {
            echo "✅ Clase Log existe\n";
            
            // Verificar métodos
            if (method_exists('Log', 'logMessage')) {
                echo "✅ Método logMessage() existe\n";
            }
            
            if (method_exists('Log', 'log')) {
                echo "❌ PROBLEMA: Método log() todavía existe\n";
            } else {
                echo "✅ Método log() NO existe (correcto)\n";
            }
        }
    } catch (Throwable $e) {
        echo "❌ Error al cargar Log.php:\n";
        echo "   " . get_class($e) . ": " . $e->getMessage() . "\n";
        echo "   Archivo: " . $e->getFile() . "\n";
        echo "   Línea: " . $e->getLine() . "\n";
    }
} else {
    echo "❌ Archivo NO existe\n";
}

echo "</pre>";
echo "<p><strong>⚠️ RECUERDA ELIMINAR ESTE ARCHIVO (test_log.php) DESPUÉS DE USAR</strong></p>";












