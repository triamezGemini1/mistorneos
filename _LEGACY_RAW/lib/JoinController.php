<?php
/**
 * Controlador para la ruta GET /join?token=...
 * Valida el token contra invitaciones/directorio_clubes y redirige:
 * - Sin token o inválido → Home con mensaje "Invitación inválida"
 * - Club con user_id → Login
 * - Club sin user_id → Guardar ID_CLUB y ENTIDAD en sesión y mostrar formulario Fast-Track (register-invited)
 */

if (!class_exists('InvitationJoinResolver')) {
    require_once __DIR__ . '/InvitationJoinResolver.php';
}

use Core\Http\Response;

class JoinController
{
    /**
     * Obtiene la URL base de la aplicación (sin barra final para concatenar rutas).
     */
    private static function getBaseUrl(): string
    {
        $base = '';
        if (class_exists('AppHelpers')) {
            $base = rtrim((string) AppHelpers::getPublicUrl(), '/');
        }
        if ($base === '' && !empty($GLOBALS['APP_CONFIG']['app']['base_url'])) {
            $base = rtrim((string) $GLOBALS['APP_CONFIG']['app']['base_url'], '/');
        }
        if ($base === '') {
            $scheme = isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $script = $_SERVER['SCRIPT_NAME'] ?? '';
            $base = $scheme . '://' . $host . (strlen($script) > 0 ? dirname($script) : '');
            $base = rtrim(str_replace('\\', '/', $base), '/');
        }
        return $base;
    }

    /**
     * Maneja GET /join?token=...
     * Retorna siempre Response (redirect o HTML). No usa exit().
     *
     * @return \Core\Http\Response
     */
    public static function handle(): Response
    {
        $token = trim((string) ($_GET['token'] ?? ''));
        $base = self::getBaseUrl();
        $baseSlash = $base . '/';

        // 1. Token no existe → Home con mensaje "Invitación inválida"
        if ($token === '') {
            return Response::redirect($baseSlash . '?error=invitacion_invalida', 302);
        }

        $resolved = InvitationJoinResolver::resolve($token);

        // 2. Token inválido o no encontrado → Home con mensaje
        if ($resolved === null) {
            return Response::redirect($baseSlash . '?error=invitacion_invalida', 302);
        }

        $idDirectorioClub = (int) ($resolved['id_directorio_club'] ?? 0);
        $requiereRegistro = !empty($resolved['requiere_registro']);

        // 3. Club ya tiene user_id → Redirigir al Login
        if (!$requiereRegistro) {
            $_SESSION['invitation_token'] = $token;
            $_SESSION['url_retorno'] = $baseSlash . 'invitation/register?token=' . urlencode($token);
            if (!headers_sent()) {
                $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
                setcookie('invitation_token', $token, time() + (7 * 86400), '/', '', $secure, true);
            }
            return Response::redirect($baseSlash . 'auth/login', 302);
        }

        // 4. Club no tiene user_id → Guardar datos en sesión y redirigir al formulario Fast-Track
        $_SESSION['invitation_token'] = $token;
        $_SESSION['url_retorno'] = $baseSlash . 'invitation/register?token=' . urlencode($token);
        $_SESSION['invitation_join_requires_register'] = true;
        $_SESSION['invitation_id_directorio_club'] = $idDirectorioClub;
        if (!headers_sent()) {
            $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
            setcookie('invitation_token', $token, time() + (7 * 86400), '/', '', $secure, true);
        }
        return Response::redirect($baseSlash . 'auth/register-invited?token=' . urlencode($token), 302);
    }
}
