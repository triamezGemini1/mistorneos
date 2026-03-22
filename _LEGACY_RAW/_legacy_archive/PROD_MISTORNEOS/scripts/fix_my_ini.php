<?php
/**
 * Script para corregir el error en my.ini
 * Elimina la línea problemática "ini,TOML"
 */

$ini_file = 'C:\wamp64\bin\mysql\mysql9.1.0\my.ini';
$backup_file = 'C:\wamp64\bin\mysql\mysql9.1.0\my.ini.backup.' . date('YmdHis');

echo "🔧 Corrección de my.ini\n";
echo "======================\n\n";

if (!file_exists($ini_file)) {
    die("❌ No se encontró el archivo: $ini_file\n");
}

// Crear backup
echo "📦 Creando backup...\n";
copy($ini_file, $backup_file);
echo "✅ Backup creado: $backup_file\n\n";

// Leer el archivo
echo "📖 Leyendo archivo...\n";
$content = file_get_contents($ini_file);
$lines = explode("\n", $content);

echo "🔍 Buscando línea problemática...\n";
$fixed_lines = [];
$removed_count = 0;

foreach ($lines as $num => $line) {
    $line_num = $num + 1;
    $trimmed = trim($line);
    
    // Eliminar líneas que contengan solo "ini,TOML" o variaciones
    if (preg_match('/^ini[,\s]*TOML$/i', $trimmed)) {
        echo "❌ Eliminando línea $line_num: $trimmed\n";
        $removed_count++;
        continue; // No agregar esta línea
    }
    
    $fixed_lines[] = $line;
}

if ($removed_count > 0) {
    // Escribir el archivo corregido
    echo "\n💾 Guardando archivo corregido...\n";
    $fixed_content = implode("\n", $fixed_lines);
    file_put_contents($ini_file, $fixed_content);
    echo "✅ Archivo corregido exitosamente\n";
    echo "✅ Se eliminaron $removed_count línea(s) problemática(s)\n\n";
    
    echo "📋 Próximos pasos:\n";
    echo "1. Reinicia MySQL desde WAMP (clic derecho > Restart Service > MySQL)\n";
    echo "2. O reinicia todo WAMP Server\n";
    echo "3. Verifica que MySQL inicie correctamente\n\n";
    
    echo "⚠️  Si algo sale mal, restaura el backup:\n";
    echo "   copy \"$backup_file\" \"$ini_file\"\n";
} else {
    echo "ℹ️  No se encontraron líneas problemáticas\n";
    echo "El problema puede estar en otro lugar\n";
}












