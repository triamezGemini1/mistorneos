<?php
/**
 * Punto de entrada al Dashboard (page=home)
 * Admin General: delega en admin_general (solo tarjetas).
 * Otros roles: delega en admin_dashboard.php.
 */
if (!defined('APP_BOOTSTRAPPED')) {
    require_once __DIR__ . '/../config/bootstrap.php';
}
require_once __DIR__ . '/../config/auth.php';

$user = Auth::user();
if ($user && ($user['role'] ?? '') === 'admin_general') {
    require __DIR__ . '/admin_general/actions/home.php';
    return;
}
require __DIR__ . '/admin_dashboard.php';
