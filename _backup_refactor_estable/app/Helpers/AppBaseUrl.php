<?php

declare(strict_types=1);

/**
 * URL base del proyecto (equivalente a AppHelpers::getBaseUrl() del monolito legacy).
 */
function mn_app_base_url(): string
{
    $fromEnv = getenv('APP_URL');
    if (is_string($fromEnv) && trim($fromEnv) !== '') {
        return rtrim(trim($fromEnv), '/');
    }

    $https = !empty($_SERVER['HTTPS']) && (string) $_SERVER['HTTPS'] !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));

    // .../mistorneos/public/index.php → base .../mistorneos
    if (str_contains($scriptName, '/public/')) {
        $publicDir = dirname($scriptName);
        $basePath = dirname($publicDir);

        return $scheme . '://' . $host . ($basePath === '/' ? '' : $basePath);
    }

    $dir = dirname($scriptName);
    if ($dir !== '/' && $dir !== '.' && $dir !== '') {
        return $scheme . '://' . $host . $dir;
    }

    return $scheme . '://' . $host;
}

/**
 * Compatibilidad con landing legacy (config.php).
 */
function app_base_url(): string
{
    return mn_app_base_url();
}
