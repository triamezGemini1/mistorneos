<?php
/** Sesión lo antes posible; sin includes previos para no enviar salida antes de session_start(). */
if (session_status() === PHP_SESSION_ACTIVE) return;
$path = '/';
if (!empty($_SERVER['SCRIPT_NAME'])) {
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    if ($dir !== '.' && $dir !== '' && $dir !== '/') $path = '/' . trim($dir, '/') . '/';
}
if ($path === '//') $path = '/';
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
session_set_cookie_params(['lifetime' => 0, 'path' => $path, 'domain' => '', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
session_name(getenv('SESSION_NAME') ?: 'mistorneos_session');
session_start();
if (!isset($_SESSION['created'])) $_SESSION['created'] = time();
elseif (time() - $_SESSION['created'] > 1800) { session_regenerate_id(true); $_SESSION['created'] = time(); }
