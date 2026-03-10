<?php
/**
 * Cerrar sesión: usar el mismo inicio de sesión que index (session_start_early) para
 * que la cookie se reconozca y se elimine correctamente (path=/ vs URL_BASE).
 */
require_once __DIR__ . '/../config/session_start_early.php';
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../lib/app_helpers.php';
require_once __DIR__ . '/../config/auth.php';

Auth::logout();

$login_url = rtrim(AppHelpers::getRequestEntryUrl(), '/') . '/login.php';
if (!headers_sent()) {
    header('Location: ' . $login_url, true, 302);
}
exit;
