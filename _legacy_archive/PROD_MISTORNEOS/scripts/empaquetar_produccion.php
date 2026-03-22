<?php
/**
 * Empaqueta la aplicación para producción en la carpeta PROD_MISTORNEOS
 * Excluye: .env, .git, logs, configuraciones de desarrollo, etc.
 */

$base_dir = dirname(__DIR__);
$dest_dir = $base_dir . DIRECTORY_SEPARATOR . 'PROD_MISTORNEOS';

// Exclusiones (patrones de ruta relativa al proyecto)
$excluir_patrones = [
    '.git',
    '.gitignore',
    '.deployignore',
    '.cursorrules',
    '.cursorrules.md',
    '.cursor',
    '.htaccess.backup',
    '.htaccess.maintenance',
    '.env',
    '.env.local',
    '.env.development',
    'node_modules',
    'vendor',
    'PROD_MISTORNEOS',
    'confiprrod.php',
    'config/config.development.php',
    'config/env.production.php', // Puede contener credenciales reales
    'tests',
    'phpunit.xml',
    'composer.json',
    'composer.lock',
    'package.json',
    'package-lock.json',
    '.DS_Store',
    'Thumbs.db',
];

$excluir_ext = ['.zip', '.sql.backup', '.tmp', '.bak', '.log'];

// Nombre de directorios/carpetas a excluir
$excluir_carpetas = ['storage/logs', 'storage/cache', 'storage/sessions', 'storage/rate_limits'];

// Excluir contenido de logs pero mantener .gitkeep si existe
$excluir_contenido_carpetas = ['storage/logs', 'storage/cache', 'storage/sessions', 'storage/rate_limits'];

function shouldExclude($rel, $excluir_patrones, $excluir_ext, $excluir_carpetas) {
    $rel = str_replace('\\', '/', $rel);
    $parts = explode('/', $rel);
    
    // Exclusiones exactas de carpeta o archivo
    foreach ($excluir_patrones as $pat) {
        if (stripos($rel, $pat) === 0 || $rel === $pat) return true;
        if (in_array($pat, $parts)) return true;
    }
    
    // Excluir .env* excepto env.production.example
    if (preg_match('#\.env(\.|$)#', $rel) && !preg_match('#env\.production\.example#', $rel)) {
        return true;
    }
    
    // Extensiones
    foreach ($excluir_ext as $ext) {
        if (substr($rel, -strlen($ext)) === $ext) return true;
    }
    
    // Carpetas cuyo contenido excluimos (mantener carpeta vacía con .gitkeep)
    foreach ($excluir_carpetas as $carp) {
        if (strpos($rel, $carp . '/') === 0) {
            $resto = substr($rel, strlen($carp) + 1);
            if ($resto !== '.gitkeep' && $resto !== '') return true;
        }
    }
    
    return false;
}

function copyRecursive($src, $dest, $root, $excluir_patrones, $excluir_ext, $excluir_carpetas) {
    if (!is_dir($src)) return 0;
    $count = 0;
    $items = scandir($src);
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $srcPath = $src . DIRECTORY_SEPARATOR . $item;
        $rel = substr($srcPath, strlen($root) + 1);
        $rel = str_replace('\\', '/', $rel);
        
        if (shouldExclude($rel, $excluir_patrones, $excluir_ext, $excluir_carpetas)) {
            continue;
        }
        
        $destPath = $dest . DIRECTORY_SEPARATOR . $item;
        
        if (is_dir($srcPath)) {
            if (!is_dir($destPath)) {
                mkdir($destPath, 0755, true);
            }
            $count += copyRecursive($srcPath, $destPath, $root, $excluir_patrones, $excluir_ext, $excluir_carpetas);
        } else {
            if (copy($srcPath, $destPath)) {
                $count++;
            }
        }
    }
    
    return $count;
}

echo "=== EMPAQUETADO PARA PRODUCCIÓN ===\n\n";
echo "Origen: $base_dir\n";
echo "Destino: $dest_dir\n\n";

if (is_dir($dest_dir)) {
    echo "Eliminando carpeta PROD_MISTORNEOS existente...\n";
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dest_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($dest_dir);
}

mkdir($dest_dir, 0755, true);
$count = copyRecursive($base_dir, $dest_dir, $base_dir, $excluir_patrones, $excluir_ext, $excluir_carpetas);

// Copiar env.production.example explícitamente si no se copió
$env_example = $dest_dir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'env.production.example';
if (!file_exists($env_example) && file_exists($base_dir . '/config/env.production.example')) {
    copy($base_dir . '/config/env.production.example', $env_example);
    $count++;
}

// Crear carpetas storage con .gitkeep
$storage_dirs = ['logs', 'cache', 'sessions', 'rate_limits'];
foreach ($storage_dirs as $d) {
    $dir = $dest_dir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . $d;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/.gitkeep', '');
    }
}

echo "✓ Copiados $count archivos\n";
echo "\n✅ Paquete creado en: PROD_MISTORNEOS\n";
echo "\nInstrucciones para producción:\n";
echo "1. Subir el contenido de PROD_MISTORNEOS al servidor\n";
echo "2. Copiar config/env.production.example a .env\n";
echo "3. Editar .env con credenciales de BD y APP_URL\n";
echo "4. Ejecutar migraciones SQL necesarias\n";
echo "5. Verificar permisos de carpetas upload/ y storage/\n";
