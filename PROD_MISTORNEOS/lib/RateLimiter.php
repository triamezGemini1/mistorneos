<?php
/**
 * Rate Limiter simple para formularios públicos
 * Evita envíos masivos basado en sesión
 */
class RateLimiter
{
    private const SESSION_KEY = 'form_rate_limit';
    private const DEFAULT_COOLDOWN = 30; // segundos entre envíos

    /**
     * Verifica si el usuario puede enviar (no está en período de cooldown)
     * 
     * @param string $formKey Identificador del formulario (ej: 'register', 'invitation_register')
     * @param int $cooldownSeconds Segundos mínimos entre envíos
     * @return bool true si puede enviar, false si debe esperar
     */
    public static function canSubmit(string $formKey, int $cooldownSeconds = self::DEFAULT_COOLDOWN): bool
    {
        $key = self::SESSION_KEY . '_' . $formKey;
        $lastTime = $_SESSION[$key] ?? 0;
        return (time() - $lastTime) >= $cooldownSeconds;
    }

    /**
     * Marca que se realizó un envío exitoso
     */
    public static function recordSubmit(string $formKey): void
    {
        $_SESSION[self::SESSION_KEY . '_' . $formKey] = time();
    }

    /**
     * Obtiene segundos restantes hasta poder enviar de nuevo
     */
    public static function secondsRemaining(string $formKey, int $cooldownSeconds = self::DEFAULT_COOLDOWN): int
    {
        $key = self::SESSION_KEY . '_' . $formKey;
        $lastTime = $_SESSION[$key] ?? 0;
        $elapsed = time() - $lastTime;
        $remaining = $cooldownSeconds - $elapsed;
        return max(0, $remaining);
    }
}
