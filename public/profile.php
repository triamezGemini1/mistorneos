<?php
/**
 * Mi Perfil: acceso directo para cualquier usuario autenticado.
 * No redirige a index.php, así se evita la redirección por rol (ej. usuario → user_portal).
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/app_helpers.php';

$user = Auth::user();
if (!$user) {
    $login_url = class_exists('AppHelpers') ? AppHelpers::url('login.php') : 'login.php';
    header('Location: ' . $login_url, true, 302);
    exit;
}

$page = 'users/profile';
include __DIR__ . '/includes/layout.php';
