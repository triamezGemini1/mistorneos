<?php
/**
 * Inscripción por invitación: punto de entrada.
 * Separa lógica de negocio (InvitationRegisterContext) y acciones POST (invitation_register_actions)
 * de la vista (invitation_register_view.php). No modifica validaciones de token ni usuario existentes.
 */
require_once __DIR__ . '/../lib/image_helper.php';
require_once __DIR__ . '/../public/simple_image_config.php';
if (!class_exists('AppHelpers')) {
    require_once __DIR__ . '/../lib/app_helpers.php';
}
require_once __DIR__ . '/../lib/InvitationRegisterContext.php';

// POST: delegar a archivo de acciones y terminar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['retirar', 'register_player', 'register_pair'], true)) {
    require_once __DIR__ . '/invitation_register_actions.php';
    exit;
}

$data = InvitationRegisterContext::load();
extract($data);

// Mensajes tras redirección (actions envía ?success= o ?error=)
if (!empty($_GET['success'])) {
    $success_message = (string) $_GET['success'];
}
if (!empty($_GET['error'])) {
    $error_message = (string) $_GET['error'];
}

include __DIR__ . '/invitation_register_view.php';
