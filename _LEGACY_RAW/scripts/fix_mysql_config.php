<?php
/**
 * Script para diagnosticar y ayudar a corregir el problema de MySQL
 * 
 * El error "--ini,TOML" sugiere un problema en my.ini
 */

echo "🔍 Diagnóstico de Configuración MySQL\n";
echo "=====================================\n\n";

$mysql_path = 'C:\wamp64\bin\mysql\mysql9.1.0';
$ini_file = $mysql_path . '\my.ini';

if (!file_exists($ini_file)) {
    echo "❌ No se encontró my.ini en: $ini_file\n";
    echo "\nBuscando en otras ubicaciones...\n";
    
    $possible_locations = [
        'C:\wamp64\bin\mysql\mysql9.1.0\my.ini',
        'C:\wamp64\bin\mysql\mysql9.1.0\my.cnf',
        'C:\wamp64\bin\mysql\mysql9.1.0\data\my.ini',
        'C:\ProgramData\MySQL\MySQL Server 9.1\my.ini',
    ];
    
    foreach ($possible_locations as $loc) {
        if (file_exists($loc)) {
            echo "✅ Encontrado en: $loc\n";
            $ini_file = $loc;
            break;
        }
    }
}

if (file_exists($ini_file)) {
    echo "✅ Archivo encontrado: $ini_file\n\n";
    
    echo "=== Buscando problema '--ini,TOML' ===\n";
    $content = file_get_contents($ini_file);
    
    // Buscar líneas problemáticas
    $lines = explode("\n", $content);
    $problem_lines = [];
    
    foreach ($lines as $num => $line) {
        $line_num = $num + 1;
        if (stripos($line, 'ini') !== false && stripos($line, 'TOML') !== false) {
            $problem_lines[] = $line_num;
            echo "⚠️  Línea $line_num: " . trim($line) . "\n";
        }
        if (preg_match('/--ini[,\s]*TOML/i', $line)) {
            $problem_lines[] = $line_num;
            echo "❌ Línea $line_num (PROBLEMA): " . trim($line) . "\n";
        }
    }
    
    if (empty($problem_lines)) {
        echo "✅ No se encontró el patrón '--ini,TOML' explícitamente\n";
        echo "\nEl problema puede estar en:\n";
        echo "1. Una línea con formato incorrecto cerca de opciones de configuración\n";
        echo "2. Un problema de codificación del archivo\n";
        echo "3. Una opción mal escrita\n";
    }
    
    echo "\n=== Recomendaciones ===\n";
    echo "1. Abre el archivo my.ini con un editor de texto (Notepad++ recomendado)\n";
    echo "2. Busca líneas que contengan 'ini' o 'TOML'\n";
    echo "3. Verifica que no haya comas o caracteres extraños\n";
    echo "4. Guarda el archivo y reinicia MySQL\n";
    echo "\nUbicación del archivo: $ini_file\n";
    
} else {
    echo "❌ No se pudo encontrar my.ini\n";
    echo "\nUbicaciones comunes:\n";
    echo "- C:\\wamp64\\bin\\mysql\\mysql9.1.0\\my.ini\n";
    echo "- C:\\wamp64\\bin\\mysql\\mysql9.1.0\\my.cnf\n";
    echo "\nPuedes buscarlo manualmente o reinstalar MySQL en WAMP\n";
}

echo "\n=== Solución Alternativa ===\n";
echo "Si el problema persiste, puedes:\n";
echo "1. Hacer backup de my.ini actual\n";
echo "2. Usar la configuración por defecto de MySQL 9.1\n";
echo "3. O reinstalar MySQL desde WAMP\n";












