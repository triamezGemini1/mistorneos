<?php

declare(strict_types=1);

/**
 * Bootstrap para módulos exclusivos de admin_general.
 * Verifica rol y carga dependencias comunes.
 */
if (!defined('APP_BOOTSTRAPPED')) {
    require_once __DIR__ . '/../../config/bootstrap.php';
}
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/db.php';
Auth::requireRole(['admin_general']);
