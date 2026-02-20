<?php
/**
 * Cerrar sesión Desktop: limpia sesión y cookie y redirige al login.
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $p['path'] ?? '/', $p['domain'] ?? '', $p['secure'] ?? false, $p['httponly'] ?? true);
}
@session_destroy();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Location: login_local.php');
exit;
