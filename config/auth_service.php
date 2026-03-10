<?php
/**
 * Servicio centralizado de sesión y control de acceso.
 * Las páginas de gestión deben invocar AuthService::requireAuth() antes de cargar
 * recursos pesados (BD, layout, módulos) para mantener TTFB bajo y evitar trabajo innecesario.
 *
 * Uso:
 *   require_once __DIR__ . '/../config/auth_service.php';
 *   AuthService::requireAuth();  // redirige a login si no hay sesión
 */
if (!defined('APP_BOOTSTRAPPED')) {
    require_once __DIR__ . '/bootstrap.php';
}
require_once __DIR__ . '/auth.php';

class AuthService {

    /**
     * Exige sesión válida; si no hay usuario logueado, redirige a login y termina.
     * Llamar al inicio de las páginas de gestión (antes de cargar BD/layout).
     */
    public static function requireAuth(): void {
        $user = Auth::user();
        if ($user !== null && is_array($user) && !empty($user)) {
            return;
        }
        if (headers_sent()) {
            return;
        }
        $login_url = self::loginUrl();
        if (function_exists('getenv') && getenv('SESSION_DEBUG')) {
            error_log('[SESSION] AuthService::requireAuth -> redirect a login | url=' . $login_url);
        }
        header('Location: ' . $login_url, true, 302);
        exit;
    }

    /**
     * URL absoluta o con base para login (subcarpeta respetada).
     */
    public static function loginUrl(): string {
        if (defined('URL_BASE') && URL_BASE !== '' && URL_BASE !== '/') {
            return rtrim(URL_BASE, '/') . '/login.php';
        }
        $base = '';
        if (!empty($_SERVER['SCRIPT_NAME'])) {
            $dir = dirname($_SERVER['SCRIPT_NAME']);
            if ($dir !== '.' && $dir !== '' && $dir !== '/') {
                $base = rtrim(str_replace('\\', '/', $dir), '/') . '/';
            }
        }
        return $base !== '' ? $base . 'login.php' : '/login.php';
    }

    /**
     * Indica si hay un usuario con sesión válida.
     */
    public static function isLoggedIn(): bool {
        $u = Auth::user();
        return $u !== null && is_array($u) && !empty($u);
    }
}
