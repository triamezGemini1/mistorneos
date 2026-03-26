<?php
require_once __DIR__ . '/../config/session_start_early.php';
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/auth_service.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';

AuthService::requireAuth();
$user = Auth::user();

$role_original = (string)($user['role_original'] ?? $user['role'] ?? '');
if ($role_original !== 'admin_general') {
    header('Location: index.php?page=home');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: index.php?page=home');
    exit;
}

CSRF::validate();

$mode = isset($_POST['role_mode']) ? (int)$_POST['role_mode'] : 0;
$allowed = [0, 1, 2, 3, 4];
if (!in_array($mode, $allowed, true)) {
    $mode = 0;
}

$_SESSION['role_switch_mode'] = $mode;

$return_to = trim((string)($_POST['return_to'] ?? ''));
$target = 'index.php?page=home';
if ($return_to !== '' && !preg_match('#^(https?|javascript|data):#i', $return_to)) {
    $target = $return_to;
}

header('Location: ' . $target);
exit;
