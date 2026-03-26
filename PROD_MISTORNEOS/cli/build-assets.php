<?php



/**
 * Script de Build de Assets
 * 
 * Compila y optimiza CSS y JavaScript
 * 
 * Uso: php cli/build-assets.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Lib\Assets\AssetsPipeline;

echo "?? Compilando Assets...\n\n";

$publicPath = __DIR__ . '/../public/assets';
$cachePath = __DIR__ . '/../storage/cache/assets';

// Crear directorio de cache si no existe
if (!is_dir($cachePath)) {
    mkdir($cachePath, 0755, true);
}

$pipeline = new AssetsPipeline($publicPath, $cachePath, true, true);

// Registrar assets CSS
echo "?? Registrando CSS...\n";
$pipeline->css('app', [
    'css/bootstrap.min.css',
    'css/app.css',
    'css/dashboard.css'
]);

$pipeline->css('auth', [
    'css/bootstrap.min.css',
    'css/auth.css'
]);

// Registrar assets JavaScript
echo "?? Registrando JavaScript...\n";
$pipeline->js('app', [
    'js/bootstrap.bundle.min.js',
    'js/app.js'
]);

$pipeline->js('charts', [
    'js/chart.min.js',
    'js/dashboard-charts.js'
]);

// Guardar manifest
$pipeline->saveManifest();

// Construir assets
echo "?? Compilando CSS...\n";
try {
    $appCss = $pipeline->cssUrl('app');
    echo "  ? app.css -> {$appCss}\n";
    
    $authCss = $pipeline->cssUrl('auth');
    echo "  ? auth.css -> {$authCss}\n";
} catch (Exception $e) {
    echo "  ??  {$e->getMessage()}\n";
}

echo "\n?? Compilando JavaScript...\n";
try {
    $appJs = $pipeline->jsUrl('app');
    echo "  ? app.js -> {$appJs}\n";
    
    $chartsJs = $pipeline->jsUrl('charts');
    echo "  ? charts.js -> {$chartsJs}\n";
} catch (Exception $e) {
    echo "  ??  {$e->getMessage()}\n";
}

echo "\n? Assets compilados exitosamente!\n";
echo "?? Archivos generados en: {$cachePath}\n";







