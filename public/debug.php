<?php
/**
 * Script de diagnóstico temporal
 * ELIMINAR DESPUÉS DE USAR
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<h1>Diagnóstico del Sistema</h1>";
echo "<pre>";

echo "PHP Version: " . phpversion() . "\n\n";

echo "=== Verificando archivos ===\n";
$files_to_check = [
    'config/bootstrap.php',
    'lib/Log.php',
    'config/environment.php',
    'config/db.php'
];

foreach ($files_to_check as $file) {
    $path = __DIR__ . '/../' . $file;
    if (file_exists($path)) {
        echo "✅ $file existe\n";
        
        // Verificar sintaxis
        $output = [];
        $return = 0;
        exec("php -l \"$path\" 2>&1", $output, $return);
        if ($return === 0) {
            echo "   ✅ Sintaxis correcta\n";
        } else {
            echo "   ❌ Error de sintaxis:\n";
            echo "   " . implode("\n   ", $output) . "\n";
        }
    } else {
        echo "❌ $file NO existe\n";
    }
}

echo "\n=== Verificando Log.php ===\n";
$log_file = __DIR__ . '/../lib/Log.php';
if (file_exists($log_file)) {
    $content = file_get_contents($log_file);
    
    // Verificar si existe método log() estático
    if (preg_match('/public static function log\s*\(/', $content)) {
        echo "❌ PROBLEMA: Método log() estático encontrado en Log.php\n";
        echo "   Esto causa error en PHP 8.0+\n";
    } else {
        echo "✅ No se encontró método log() estático problemático\n";
    }
    
    // Verificar si existe logMessage()
    if (preg_match('/public static function logMessage\s*\(/', $content)) {
        echo "✅ Método logMessage() encontrado (correcto)\n";
    }
}

echo "\n=== Intentando cargar bootstrap.php ===\n";
try {
    define('APP_BOOTSTRAPPED', false);
    require_once __DIR__ . '/../config/bootstrap.php';
    echo "✅ bootstrap.php cargado correctamente\n";
} catch (Throwable $e) {
    echo "❌ Error al cargar bootstrap.php:\n";
    echo "   " . get_class($e) . ": " . $e->getMessage() . "\n";
    echo "   Archivo: " . $e->getFile() . "\n";
    echo "   Línea: " . $e->getLine() . "\n";
    echo "   Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Verificando OPcache ===\n";
if (function_exists('opcache_get_status')) {
    $status = opcache_get_status();
    if ($status) {
        echo "✅ OPcache habilitado\n";
        echo "   Scripts en caché: " . $status['opcache_statistics']['num_cached_scripts'] . "\n";
        echo "   Memoria usada: " . round($status['memory_usage']['used_memory'] / 1024 / 1024, 2) . " MB\n";
    }
} else {
    echo "ℹ️ OPcache no está habilitado\n";
}

echo "</pre>";
echo "<p><strong>⚠️ RECUERDA ELIMINAR ESTE ARCHIVO (debug.php) DESPUÉS DE USAR</strong></p>";












