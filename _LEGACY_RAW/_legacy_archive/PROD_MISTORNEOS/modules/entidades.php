<?php

require_once __DIR__ . '/../config/auth.php';

Auth::requireRole(['admin_general']);

$action = $_GET['action'] ?? 'index';

if ($action === 'detail') {
    include_once __DIR__ . '/entidades/detail.php';
    return;
}

include_once __DIR__ . '/admin_general/entidades/actions/index.php';
