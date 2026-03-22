<?php
/**
 * Modulo de Notificaciones Masivas
 * WhatsApp, Email, Telegram
 */
$action = $_GET['action'] ?? 'list';

if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/notificaciones_masivas/send.php';
} else {
    require_once __DIR__ . '/notificaciones_masivas/list.php';
}
