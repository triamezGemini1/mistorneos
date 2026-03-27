<?php
declare(strict_types=1);

/**
 * Post-vuelo de migración:
 * - Limpia archivos de cache de la app (storage/cache)
 * - Invalida sesiones activas no-admin (mantiene sesiones que contengan admin_general)
 *
 * Uso:
 *   php scripts/post_migration_cleanup.php
 */

require_once __DIR__ . '/../config/bootstrap.php';

function rrmdirFilesOnly(string $dir): array
{
    $deleted = 0;
    $errors = 0;
    if (!is_dir($dir)) {
        return ['deleted' => 0, 'errors' => 0];
    }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($it as $item) {
        if ($item->isFile()) {
            $name = $item->getFilename();
            if ($name === '.gitignore' || $name === '.htaccess') {
                continue;
            }
            if (@unlink($item->getPathname())) {
                $deleted++;
            } else {
                $errors++;
            }
        }
    }

    return ['deleted' => $deleted, 'errors' => $errors];
}

function normalizeSessionPath(string $raw): string
{
    // Formato posible: "N;/path" (por ejemplo "2;/var/lib/php/sessions")
    if (strpos($raw, ';') !== false) {
        $parts = explode(';', $raw);
        return trim((string)end($parts));
    }
    return trim($raw);
}

function cleanupSessionsExceptAdmin(): array
{
    $rawPath = (string)ini_get('session.save_path');
    $sessionPath = normalizeSessionPath($rawPath);
    if ($sessionPath === '') {
        $sessionPath = sys_get_temp_dir();
    }

    $deleted = 0;
    $kept = 0;
    $errors = 0;
    $scanned = 0;

    if (!is_dir($sessionPath)) {
        return [
            'path' => $sessionPath,
            'scanned' => 0,
            'deleted' => 0,
            'kept' => 0,
            'errors' => 0,
        ];
    }

    $files = glob(rtrim($sessionPath, '/\\') . DIRECTORY_SEPARATOR . 'sess_*') ?: [];
    foreach ($files as $file) {
        if (!is_file($file)) {
            continue;
        }
        $scanned++;
        $content = @file_get_contents($file);
        if ($content === false) {
            $errors++;
            continue;
        }

        // Mantener sesiones de admin_general.
        $isAdminSession = strpos($content, 'admin_general') !== false;
        if ($isAdminSession) {
            $kept++;
            continue;
        }

        if (@unlink($file)) {
            $deleted++;
        } else {
            $errors++;
        }
    }

    return [
        'path' => $sessionPath,
        'scanned' => $scanned,
        'deleted' => $deleted,
        'kept' => $kept,
        'errors' => $errors,
    ];
}

$cacheDir = __DIR__ . '/../storage/cache';
$cacheResult = rrmdirFilesOnly($cacheDir);
$sessionResult = cleanupSessionsExceptAdmin();

echo "=== POST MIGRATION CLEANUP ===\n";
echo "Cache dir: {$cacheDir}\n";
echo "Cache files deleted: {$cacheResult['deleted']}\n";
echo "Cache errors: {$cacheResult['errors']}\n\n";

echo "Session path: {$sessionResult['path']}\n";
echo "Sessions scanned: {$sessionResult['scanned']}\n";
echo "Sessions deleted (non-admin): {$sessionResult['deleted']}\n";
echo "Sessions kept (admin_general): {$sessionResult['kept']}\n";
echo "Session errors: {$sessionResult['errors']}\n";
