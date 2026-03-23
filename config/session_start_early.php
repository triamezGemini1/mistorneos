<?php
/** Sesión lo antes posible; sin includes previos para no enviar salida antes de session_start(). */
$session_debug = getenv('SESSION_DEBUG');
if (session_status() === PHP_SESSION_ACTIVE) {
    if ($session_debug) error_log('[SESSION_DEBUG] session_start_early.php | sesión ya activa, saliendo');
    return;
}
if (headers_sent()) {
    if ($session_debug) error_log('[SESSION_DEBUG] session_start_early.php | headers already sent, skip');
    return;
}

// Duración en servidor y cookie (evita "sesión expirada" con el panel abierto o importaciones largas)
require_once __DIR__ . '/session_env_read.php';
$sessionTimes = session_read_lifetime_from_env();
ini_set('session.gc_maxlifetime', (string) $sessionTimes['gc']);

// Usar path='/' para que la cookie se envíe en toda la ruta (evita pérdida de sesión en subcarpetas tipo /mistorneos_beta/public/)
$path = '/';
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
session_set_cookie_params([
    'lifetime' => $sessionTimes['cookie'],
    'path' => $path,
    'domain' => '',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
$sname = !empty($sessionTimes['name']) ? (string)$sessionTimes['name'] : (getenv('SESSION_NAME') ?: 'mistorneos_session');
session_name($sname);
session_start();
if ($session_debug) error_log('[SESSION_DEBUG] session_start_early.php | session_start OK | path=' . $path . ' | name=' . $sname . ' | id=' . session_id() . ' | cookie_enviada=' . (isset($_COOKIE[$sname]) ? 'si' : 'no'));
if (!isset($_SESSION['created'])) $_SESSION['created'] = time();
elseif (time() - $_SESSION['created'] > 1800) { session_regenerate_id(true); $_SESSION['created'] = time(); }
