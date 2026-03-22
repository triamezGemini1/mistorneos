<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../lib/app_helpers.php';
$url = AppHelpers::url('index.php', ['page' => 'users/profile']);
header("Location: $url");
exit;

