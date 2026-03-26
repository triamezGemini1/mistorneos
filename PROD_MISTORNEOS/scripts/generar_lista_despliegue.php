<?php
/**
 * Genera una lista completa de archivos a subir a producción
 * Excluye archivos de desarrollo y genera reporte detallado
 */

$base_dir = __DIR__ . '/..';

echo "=== GENERANDO LISTA DE ARCHIVOS PARA DESPLIEGUE ===\n\n";

// Leer .deployignore
$excluir = [];
$deployignore = $base_dir . '/.deployignore';
if (file_exists($deployignore)) {
    $lineas = file($deployignore, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lineas as $linea) {
        $linea = trim($linea);
        if ($linea && substr($linea, 0, 1) !== '#') {
            $excluir[] = $linea;
        }
    }
}

// Función para verificar si un archivo debe excluirse
function debeExcluir($ruta_relativa, $excluir) {
    foreach ($excluir as $patron) {
        // Patrón simple
        if (strpos($ruta_relativa, $patron) !== false) {
            return true;
        }
        // Patrón con wildcard
        if (fnmatch($patron, $ruta_relativa)) {
            return true;
        }
    }
    return false;
}

// Función recursiva para listar archivos
function listarArchivos($dir, $base_dir, $excluir, $nivel = 0) {
    $archivos = [];
    $items = scandir($dir);
    
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        
        $ruta_completa = $dir . '/' . $item;
        $ruta_relativa = ltrim(str_replace($base_dir . '/', '', $ruta_completa), '/');
        
        if (debeExcluir($ruta_relativa, $excluir)) {
            continue;
        }
        
        if (is_dir($ruta_completa)) {
            $archivos = array_merge($archivos, listarArchivos($ruta_completa, $base_dir, $excluir, $nivel + 1));
        } else {
            $archivos[] = [
                'ruta' => $ruta_relativa,
                'tamaño' => filesize($ruta_completa),
                'modificado' => filemtime($ruta_completa)
            ];
        }
    }
    
    return $archivos;
}

// Listar todos los archivos
$archivos = listarArchivos($base_dir, $base_dir, $excluir);

// Ordenar por ruta
usort($archivos, function($a, $b) {
    return strcmp($a['ruta'], $b['ruta']);
});

// Generar reporte
$total_archivos = count($archivos);
$total_tamaño = array_sum(array_column($archivos, 'tamaño'));
$total_mb = round($total_tamaño / 1024 / 1024, 2);

echo "ARCHIVOS A SUBIR: $total_archivos\n";
echo "TAMAÑO TOTAL: " . number_format($total_tamaño) . " bytes ($total_mb MB)\n\n";

// Agrupar por directorio
$por_directorio = [];
foreach ($archivos as $archivo) {
    $dir = dirname($archivo['ruta']);
    if ($dir == '.') $dir = 'raíz';
    if (!isset($por_directorio[$dir])) {
        $por_directorio[$dir] = [];
    }
    $por_directorio[$dir][] = $archivo;
}

// Mostrar por directorio
echo "DISTRIBUCIÓN POR DIRECTORIO:\n";
echo str_repeat("-", 60) . "\n";
foreach ($por_directorio as $dir => $archs) {
    $tamaño_dir = array_sum(array_column($archs, 'tamaño'));
    $tamaño_mb = round($tamaño_dir / 1024 / 1024, 2);
    echo "$dir/\n";
    echo "  Archivos: " . count($archs) . " | Tamaño: " . number_format($tamaño_dir) . " bytes ($tamaño_mb MB)\n";
}

// Guardar lista completa en archivo
$lista_file = $base_dir . '/lista_archivos_despliegue.txt';
$contenido = "LISTA DE ARCHIVOS PARA DESPLIEGUE A PRODUCCIÓN\n";
$contenido .= "Generado: " . date('Y-m-d H:i:s') . "\n";
$contenido .= "Total archivos: $total_archivos\n";
$contenido .= "Tamaño total: " . number_format($total_tamaño) . " bytes ($total_mb MB)\n\n";
$contenido .= str_repeat("=", 60) . "\n\n";

foreach ($archivos as $archivo) {
    $tamaño_kb = round($archivo['tamaño'] / 1024, 2);
    $fecha = date('Y-m-d H:i:s', $archivo['modificado']);
    $contenido .= sprintf("%-60s %10s KB  %s\n", 
        $archivo['ruta'], 
        number_format($tamaño_kb, 2),
        $fecha
    );
}

file_put_contents($lista_file, $contenido);

echo "\n" . str_repeat("=", 60) . "\n";
echo "✅ Lista generada exitosamente\n";
echo "Archivo: lista_archivos_despliegue.txt\n";
echo str_repeat("=", 60) . "\n";

// Mostrar archivos críticos
echo "\nARCHIVOS CRÍTICOS (verificar que existan):\n";
echo str_repeat("-", 60) . "\n";
$criticos = [
    'config/config.production.php',
    'config/bootstrap.php',
    'config/db.php',
    'config/auth.php',
    'public/index.php',
    'public/login.php',
    'sql/migracion_produccion_2026.sql',
];

foreach ($criticos as $critico) {
    $ruta = $base_dir . '/' . $critico;
    if (file_exists($ruta)) {
        echo "✓ $critico\n";
    } else {
        echo "✗ $critico (NO ENCONTRADO)\n";
    }
}

