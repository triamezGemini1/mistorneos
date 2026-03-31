<?php

declare(strict_types=1);

/**
 * URL absoluta en el sitio hacia el landing canónico.
 *
 * - Instalación en subcarpeta (.../mistorneos/public/*.php): raíz del proyecto (.../mistorneos/).
 * - DocumentRoot en public (solo /admin_torneo.php, etc.): /index.php en ese host.
 */
function mn_landing_absolute_url(): string
{
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== ''
        ? '?' . $_SERVER['QUERY_STRING']
        : '';

    if ($scriptName !== '' && str_contains($scriptName, '/public/')) {
        $publicDir = dirname($scriptName);
        $base = dirname($publicDir);
        if ($base !== '.' && $base !== '') {
            if ($base === '/') {
                return '/' . $qs;
            }

            return rtrim($base, '/') . '/' . $qs;
        }
    }

    $dir = dirname($scriptName);
    if ($dir === '/' || $dir === '.' || $dir === '' || $scriptName === '/') {
        return '/index.php' . $qs;
    }

    return $dir . '/index.php' . $qs;
}
