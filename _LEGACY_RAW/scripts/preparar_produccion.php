<?php
/**
 * Script para preparar la aplicación completa para producción
 * 
 * Este script:
 * 1. Verifica que todos los archivos necesarios existan
 * 2. Prepara la estructura para producción
 * 3. Genera lista de archivos a subir
 * 4. Verifica configuración
 */

$base_dir = __DIR__ . '/..';
$errores = [];
$advertencias = [];
$exitos = [];

echo "=== PREPARACIÓN PARA PRODUCCIÓN ===\n\n";

// 1. Verificar archivo de configuración de producción
echo "1. Verificando configuración de producción...\n";
$confiprod = $base_dir . '/confiprrod.php';
$config_prod = $base_dir . '/config/config.production.php';

if (file_exists($confiprod)) {
    echo "   ✓ confiprrod.php existe\n";
    
    // Copiar a config/config.production.php si es diferente
    if (!file_exists($config_prod) || md5_file($confiprod) !== md5_file($config_prod)) {
        copy($confiprod, $config_prod);
        echo "   ✓ config/config.production.php actualizado desde confiprrod.php\n";
        $exitos[] = "Configuración de producción actualizada";
    } else {
        echo "   ✓ config/config.production.php ya está actualizado\n";
    }
} else {
    echo "   ✗ confiprrod.php NO existe\n";
    $errores[] = "Archivo confiprrod.php no encontrado";
}

// 2. Verificar archivos críticos
echo "\n2. Verificando archivos críticos...\n";
$archivos_criticos = [
    'config/bootstrap.php',
    'config/db.php',
    'config/auth.php',
    'config/csrf.php',
    'public/index.php',
    'public/login.php',
];

foreach ($archivos_criticos as $archivo) {
    $ruta = $base_dir . '/' . $archivo;
    if (file_exists($ruta)) {
        echo "   ✓ $archivo\n";
        $exitos[] = "Archivo crítico: $archivo";
    } else {
        echo "   ✗ $archivo NO existe\n";
        $errores[] = "Archivo crítico faltante: $archivo";
    }
}

// 3. Verificar nuevos archivos
echo "\n3. Verificando archivos nuevos...\n";
$archivos_nuevos = [
    'public/inscribir_evento_masivo.php',
    'public/reportar_pago_evento_masivo.php',
    'public/ver_recibo_pago.php',
    'public/api/search_persona.php',
    'public/api/search_user_persona.php',
    'public/api/verificar_inscripcion.php',
    'modules/cuentas_bancarias.php',
    'modules/reportes_pago_usuarios.php',
    'modules/tournament_admin/podios_equipos.php',
    'modules/tournament_admin/equipos_detalle.php',
    'manuales_web/manual_usuario.php',
    'manuales_web/admin_club_resumido.html',
    'lib/BankValidator.php',
];

foreach ($archivos_nuevos as $archivo) {
    $ruta = $base_dir . '/' . $archivo;
    if (file_exists($ruta)) {
        echo "   ✓ $archivo\n";
        $exitos[] = "Archivo nuevo: $archivo";
    } else {
        echo "   ⚠ $archivo NO existe (puede ser opcional)\n";
        $advertencias[] = "Archivo nuevo no encontrado: $archivo";
    }
}

// 4. Verificar script de migración SQL
echo "\n4. Verificando migración de base de datos...\n";
$sql_migracion = $base_dir . '/sql/migracion_produccion_2026.sql';
if (file_exists($sql_migracion)) {
    echo "   ✓ sql/migracion_produccion_2026.sql existe\n";
    $tamaño = filesize($sql_migracion);
    echo "   ✓ Tamaño: " . number_format($tamaño) . " bytes\n";
    $exitos[] = "Script de migración SQL";
} else {
    echo "   ✗ sql/migracion_produccion_2026.sql NO existe\n";
    $errores[] = "Script de migración SQL no encontrado";
}

// 5. Verificar directorios necesarios
echo "\n5. Verificando directorios...\n";
$directorios = [
    'storage/logs',
    'storage/cache',
    'storage/sessions',
    'upload/tournaments',
    'upload/clubs',
    'upload/logos',
    'manuales_web/assets/images',
];

foreach ($directorios as $dir) {
    $ruta = $base_dir . '/' . $dir;
    if (is_dir($ruta)) {
        echo "   ✓ $dir/\n";
    } else {
        echo "   ⚠ $dir/ NO existe (se creará en producción si es necesario)\n";
        $advertencias[] = "Directorio no existe: $dir";
    }
}

// 6. Generar lista de archivos a excluir
echo "\n6. Generando lista de archivos a excluir...\n";
$excluir = [
    '.git',
    '.gitignore',
    '.env',
    'node_modules',
    'vendor',
    'storage/logs/*.log',
    'storage/cache/*',
    'storage/sessions/*',
    '*.zip',
    '*.sql.backup',
    'confiprrod.php', // Mantener en local, no subir
    'config/config.development.php',
    'tests',
    'phpunit.xml',
    'composer.json',
    'composer.lock',
    'package.json',
    'package-lock.json',
    '.DS_Store',
    'Thumbs.db',
];

echo "   Archivos/directorios a excluir del despliegue:\n";
foreach ($excluir as $item) {
    echo "   - $item\n";
}

// Guardar lista de exclusión
$exclusion_file = $base_dir . '/.deployignore';
file_put_contents($exclusion_file, implode("\n", $excluir));
echo "   ✓ Lista guardada en .deployignore\n";

// 7. Resumen
echo "\n" . str_repeat("=", 60) . "\n";
echo "RESUMEN\n";
echo str_repeat("=", 60) . "\n";
echo "✓ Exitosos: " . count($exitos) . "\n";
echo "⚠ Advertencias: " . count($advertencias) . "\n";
echo "✗ Errores: " . count($errores) . "\n\n";

if (count($advertencias) > 0) {
    echo "ADVERTENCIAS:\n";
    foreach ($advertencias as $adv) {
        echo "  - $adv\n";
    }
    echo "\n";
}

if (count($errores) > 0) {
    echo "ERRORES CRÍTICOS:\n";
    foreach ($errores as $error) {
        echo "  - $error\n";
    }
    echo "\n⚠️  Por favor, corrige estos errores antes de desplegar.\n";
    exit(1);
}

echo "✅ La aplicación está lista para producción.\n";
echo "\nPróximos pasos:\n";
echo "1. Ejecutar: php scripts/listar_archivos_despliegue.php\n";
echo "2. Revisar: DEPLOY_PRODUCCION_2026.md\n";
echo "3. Hacer backup de la base de datos en producción\n";
echo "4. Ejecutar migración SQL en producción\n";
echo "5. Subir archivos manteniendo estructura de directorios\n";
echo "6. Verificar: php scripts/verificar_migracion.php\n";

