<?php
// Módulo de Administradores de Club
$action = $_GET['action'] ?? 'list';

if ($action === 'invitar') {
    require_once __DIR__ . '/admin_clubs/invitar.php';
} elseif ($action === 'detail') {
    require_once __DIR__ . '/admin_clubs/detail.php';
} else {
    require_once __DIR__ . '/admin_clubs/list.php';
}


