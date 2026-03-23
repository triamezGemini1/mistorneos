<?php
/**
 * Sesión y validación de usuario logueado.
 * Uso: require_once __DIR__ . '/../core/includes/auth_check.php';
 * Inicia sesión si no está iniciada; no redirige por defecto (cada página decide).
 */
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../../config/session_start_early.php';
}
if (!function_exists('auth_user_logged')) {
    function auth_user_logged(): bool {
        return isset($_SESSION['user']) && is_array($_SESSION['user']) && !empty($_SESSION['user']);
    }
}
