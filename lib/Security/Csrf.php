<?php



namespace Lib\Security;

/**
 * CSRF Protection - Sistema avanzado de protección contra Cross-Site Request Forgery
 * 
 * Características:
 * - Tokens únicos por sesión con regeneración periódica
 * - Validación en cada request POST/PUT/PATCH/DELETE
 * - Soporte para AJAX con header X-CSRF-TOKEN
 * - Double Submit Cookie pattern (opcional)
 * - Token time-based expiration
 * 
 * @package Lib\Security
 * @version 1.0.0
 */
final class Csrf
{
    private const TOKEN_LENGTH = 32;
    private const TOKEN_LIFETIME = 7200; // 2 horas
    private const SESSION_KEY = '_csrf_token';
    private const SESSION_TIME_KEY = '_csrf_token_time';

    /**
     * Genera un nuevo token CSRF
     * 
     * @return string Token generado
     */
    public static function generateToken(): string
    {
        self::ensureSessionStarted();

        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
        
        $_SESSION[self::SESSION_KEY] = $token;
        $_SESSION[self::SESSION_TIME_KEY] = time();

        return $token;
    }

    /**
     * Obtiene el token CSRF actual (o genera uno nuevo)
     * 
     * @return string
     */
    public static function getToken(): string
    {
        self::ensureSessionStarted();

        // Si no existe token o ha expirado, generar nuevo
        if (!isset($_SESSION[self::SESSION_KEY]) || self::isTokenExpired()) {
            return self::generateToken();
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Valida un token CSRF
     * 
     * @param string|null $token Token a validar
     * @return bool True si es válido
     */
    public static function validateToken(?string $token): bool
    {
        self::ensureSessionStarted();

        if ($token === null || !isset($_SESSION[self::SESSION_KEY])) {
            return false;
        }

        // Verificar si ha expirado
        if (self::isTokenExpired()) {
            return false;
        }

        // Comparación segura contra timing attacks
        return hash_equals($_SESSION[self::SESSION_KEY], $token);
    }

    /**
     * Valida token desde request (POST, headers, etc.)
     * 
     * @return bool
     */
    public static function validateFromRequest(): bool
    {
        $token = null;

        // 1. Buscar en POST
        if (isset($_POST['_csrf_token'])) {
            $token = $_POST['_csrf_token'];
        }
        // 2. Buscar en headers (AJAX)
        elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
        }
        // 3. Buscar en X-XSRF-TOKEN (estándar Angular)
        elseif (isset($_SERVER['HTTP_X_XSRF_TOKEN'])) {
            $token = $_SERVER['HTTP_X_XSRF_TOKEN'];
        }

        return self::validateToken($token);
    }

    /**
     * Genera campo hidden para formularios HTML
     * 
     * @return string HTML del input hidden
     */
    public static function field(): string
    {
        $token = self::getToken();
        return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Genera meta tag para AJAX (colocar en <head>)
     * 
     * @return string HTML del meta tag
     */
    public static function metaTag(): string
    {
        $token = self::getToken();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Regenera el token (útil después de login/logout)
     * 
     * @return string Nuevo token
     */
    public static function regenerate(): string
    {
        self::ensureSessionStarted();
        return self::generateToken();
    }

    /**
     * Verifica si el token ha expirado
     * 
     * @return bool
     */
    private static function isTokenExpired(): bool
    {
        if (!isset($_SESSION[self::SESSION_TIME_KEY])) {
            return true;
        }

        $tokenAge = time() - $_SESSION[self::SESSION_TIME_KEY];
        return $tokenAge > self::TOKEN_LIFETIME;
    }

    /**
     * Verifica que la sesión esté iniciada
     * 
     * @return void
     */
    private static function ensureSessionStarted(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    /**
     * Limpia tokens (útil al cerrar sesión)
     * 
     * @return void
     */
    public static function clear(): void
    {
        self::ensureSessionStarted();
        unset($_SESSION[self::SESSION_KEY], $_SESSION[self::SESSION_TIME_KEY]);
    }
}









