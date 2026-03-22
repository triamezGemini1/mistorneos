<?php

declare(strict_types=1);

/**
 * URL absoluta hacia recursos bajo /public (p. ej. perfil atleta para QR).
 */
function mn_public_base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443');
    $scheme = $https ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $publicDir = dirname(dirname($script));
    if ($publicDir === '/' || $publicDir === '\\' || $publicDir === '.') {
        $publicDir = '';
    }

    return rtrim($scheme . '://' . $host . $publicDir, '/');
}

function mn_atleta_perfil_url(int $usuarioId): string
{
    if ($usuarioId <= 0) {
        return '';
    }

    return mn_public_base_url() . '/atleta.php?id=' . $usuarioId;
}
