<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/config/bootstrap.php';

$_SESSION = [];

if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

$script = $_SERVER['SCRIPT_NAME'] ?? '';
$target = str_contains($script, '/public/') ? 'index.php' : 'public/index.php';
header('Location: ' . $target, true, 303);
exit;
