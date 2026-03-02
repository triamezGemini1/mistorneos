<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/session_start_early.php';
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/app_helpers.php';

$user = Auth::user();
if (!$user) {
    $login_url = class_exists('AppHelpers') ? AppHelpers::url('login.php') : 'login.php';
    $sep = (strpos($login_url, '?') !== false) ? '&' : '?';
    header('Location: ' . $login_url . $sep . 'return_url=' . rawurlencode('profile.php'), true, 302);
    exit;
}

$page = 'users/profile';
include __DIR__ . '/includes/layout.php';
