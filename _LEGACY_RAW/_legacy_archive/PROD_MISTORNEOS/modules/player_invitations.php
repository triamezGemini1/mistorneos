<?php
// Módulo de Invitaciones a Jugadores
$action = $_GET['action'] ?? 'list';

if ($action === 'send') {
    require_once __DIR__ . '/player_invitations/send_whatsapp.php';
} else {
    require_once __DIR__ . '/player_invitations/list.php';
}


