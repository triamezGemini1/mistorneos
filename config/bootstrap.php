<?php

declare(strict_types=1);

/**
 * Arranque mínimo: sesión y helpers comunes.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

require dirname(__DIR__) . '/app/Helpers/Csrf.php';
