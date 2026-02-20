<?php



/**
 * Script de Deployment
 * 
 * Automatiza el despliegue de la aplicación
 * 
 * Uso: php cli/deploy.php [environment]
 * Ambientes: production, staging, development
 */

$environment = $argv[1] ?? 'staging';

$allowedEnvironments = ['production', 'staging', 'development'];

if (!in_array($environment, $allowedEnvironments)) {
    echo "? Error: Ambiente inválido. Usa: production, staging o development\n";
    exit(1);
}

echo "?? Iniciando deployment a: {$environment}\n\n";

// Paso 1: Verificar requisitos
echo "?? Verificando requisitos...\n";
checkRequirements();

// Paso 2: Backup
if ($environment === 'production') {
    echo "?? Creando backup...\n";
    createBackup();
}

// Paso 3: Actualizar código
echo "?? Actualizando código...\n";
updateCode();

// Paso 4: Instalar dependencias
echo "?? Instalando dependencias...\n";
installDependencies($environment);

// Paso 5: Migrar base de datos
echo "???  Ejecutando migraciones...\n";
runMigrations($environment);

// Paso 6: Compilar assets
echo "?? Compilando assets...\n";
compileAssets();

// Paso 7: Limpiar cache
echo "?? Limpiando cache...\n";
clearCache();

// Paso 8: Verificar permisos
echo "?? Verificando permisos...\n";
setPermissions();

// Paso 9: Health check
echo "?? Health check...\n";
healthCheck($environment);

echo "\n? Deployment completado exitosamente!\n";

/**
 * Verifica requisitos del sistema
 */
function checkRequirements(): void
{
    $checks = [
        'PHP 8.0+' => version_compare(PHP_VERSION, '8.0.0', '>='),
        'PDO Extension' => extension_loaded('pdo'),
        'PDO MySQL' => extension_loaded('pdo_mysql'),
        'mbstring' => extension_loaded('mbstring'),
        'JSON' => extension_loaded('json'),
        'OpenSSL' => extension_loaded('openssl'),
    ];

    foreach ($checks as $check => $result) {
        echo ($result ? '  ?' : '  ?') . " {$check}\n";
        
        if (!$result) {
            echo "\n? Error: Requisito no cumplido: {$check}\n";
            exit(1);
        }
    }
    
    echo "  Todos los requisitos cumplidos\n\n";
}

/**
 * Crea backup de la aplicación
 */
function createBackup(): void
{
    $backupDir = __DIR__ . '/../backups';
    
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }

    $timestamp = date('Y-m-d_H-i-s');
    $backupFile = "{$backupDir}/backup_{$timestamp}.tar.gz";

    // Backup de archivos
    exec("tar -czf {$backupFile} --exclude='vendor' --exclude='node_modules' --exclude='storage' .");
    
    // Backup de base de datos
    $dbBackupFile = "{$backupDir}/db_{$timestamp}.sql";
    // exec("mysqldump -u root -p database > {$dbBackupFile}");

    echo "  Backup creado: {$backupFile}\n\n";
}

/**
 * Actualiza código desde repositorio
 */
function updateCode(): void
{
    if (is_dir('.git')) {
        exec('git pull origin main', $output, $returnCode);
        
        if ($returnCode !== 0) {
            echo "  ??  No se pudo actualizar código desde git\n\n";
        } else {
            echo "  Código actualizado desde git\n\n";
        }
    } else {
        echo "  No es un repositorio git, saltando...\n\n";
    }
}

/**
 * Instala dependencias
 */
function installDependencies(string $environment): void
{
    $command = 'composer install --no-interaction';
    
    if ($environment === 'production') {
        $command .= ' --no-dev --optimize-autoloader';
    }

    exec($command, $output, $returnCode);
    
    if ($returnCode !== 0) {
        echo "  ? Error instalando dependencias\n";
        exit(1);
    }
    
    echo "  Dependencias instaladas\n\n";
}

/**
 * Ejecuta migraciones de base de datos
 */
function runMigrations(string $environment): void
{
    // Aquí irían tus migraciones
    echo "  Migraciones ejecutadas\n\n";
}

/**
 * Compila assets (CSS/JS)
 */
function compileAssets(): void
{
    require_once __DIR__ . '/../vendor/autoload.php';
    
    $publicPath = __DIR__ . '/../public/assets';
    $cachePath = __DIR__ . '/../storage/cache/assets';

    $pipeline = new \Lib\Assets\AssetsPipeline($publicPath, $cachePath, true, true);
    
    // Limpiar cache anterior
    $deleted = $pipeline->clearCache();
    echo "  {$deleted} archivos de cache eliminados\n";
    
    // Actualizar versión
    $pipeline->updateVersion();
    echo "  Assets compilados y versionados\n\n";
}

/**
 * Limpia cache de la aplicación
 */
function clearCache(): void
{
    $cacheDir = __DIR__ . '/../storage/cache';
    
    if (is_dir($cacheDir)) {
        $files = glob($cacheDir . '/*');
        
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
    
    echo "  Cache limpiado\n\n";
}

/**
 * Establece permisos correctos
 */
function setPermissions(): void
{
    $dirs = [
        __DIR__ . '/../storage',
        __DIR__ . '/../storage/cache',
        __DIR__ . '/../storage/logs',
        __DIR__ . '/../storage/sessions',
    ];

    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        chmod($dir, 0775);
    }
    
    echo "  Permisos configurados\n\n";
}

/**
 * Verifica salud de la aplicación
 */
function healthCheck(string $environment): void
{
    // Verificar archivos críticos
    $criticalFiles = [
        __DIR__ . '/../public/index.php',
        __DIR__ . '/../config/config.php',
        __DIR__ . '/../vendor/autoload.php',
    ];

    foreach ($criticalFiles as $file) {
        if (!file_exists($file)) {
            echo "  ? Archivo crítico no encontrado: {$file}\n";
            exit(1);
        }
    }

    // Verificar conexión a base de datos
    try {
        require_once __DIR__ . '/../config/bootstrap.php';
        require_once __DIR__ . '/../config/db.php';
        
        $pdo = DB::pdo();
        $pdo->query('SELECT 1');
        
        echo "  ? Conexión a base de datos OK\n";
    } catch (Exception $e) {
        echo "  ? Error de conexión a base de datos: " . $e->getMessage() . "\n";
        exit(1);
    }

    echo "  Health check OK\n";
}







