<?php

declare(strict_types=1);

/**
 * Sesión de usuario atleta (clave $_SESSION['user']).
 * El panel administrador usa $_SESSION['admin_user'] y no cuenta aquí como sesión de atleta.
 */
final class AuthHelper
{
    public static function isLoggedIn(): bool
    {
        $u = $_SESSION['user'] ?? null;

        return is_array($u) && !empty($u['id']);
    }

    /**
     * @return array{id:int,username:string,email:string,role:string,nombre?:string,cedula?:string,nacionalidad?:string}|null
     */
    public static function currentUser(): ?array
    {
        if (!self::isLoggedIn()) {
            return null;
        }

        $u = $_SESSION['user'];

        return is_array($u) ? $u : null;
    }

    public static function requireUser(): void
    {
        if (self::isLoggedIn()) {
            return;
        }

        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $target = str_contains($script, '/public/') ? 'index.php' : 'public/index.php';
        header('Location: ' . $target . '?acceso=restringido', true, 303);
        exit;
    }
}
