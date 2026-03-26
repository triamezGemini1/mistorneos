<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/session_start_early.php';
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../config/auth_service.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../lib/app_helpers.php';
AuthService::requireAuth();
$user = Auth::user();

$page = 'users/profile';
include __DIR__ . '/includes/layout.php';
