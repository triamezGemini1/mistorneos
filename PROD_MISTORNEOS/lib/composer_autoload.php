<?php
declare(strict_types=1);

/**
 * Carga única de vendor/autoload.php desde la raíz del proyecto (directorio padre de /lib).
 */
function mistorneos_composer_autoload_path(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
}

function mistorneos_load_composer_autoload(): bool
{
    static $loaded = false;
    if ($loaded) {
        return true;
    }
    $path = mistorneos_composer_autoload_path();
    $real = realpath($path);
    if ($real !== false && is_readable($real)) {
        require_once $real;
        $loaded = true;
        return true;
    }
    if (is_readable($path)) {
        require_once $path;
        $loaded = true;
        return true;
    }
    return false;
}
