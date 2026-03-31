<?php



namespace Core\Middleware;

use Core\Http\Request;
use Core\Http\Response;

/**
 * Auth Middleware - Verifica autenticación del usuario
 * 
 * @package Core\Middleware
 * @version 1.0.0
 */
class AuthMiddleware implements Middleware
{
    /**
     * Constructor
     * 
     * @param array $roles Roles permitidos (opcional, null = cualquier autenticado)
     */
    public function __construct(private ?array $roles = null)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Request $request, callable $next): Response
    {
        // Verificar si hay sesión iniciada
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // Verificar autenticación
        if (!isset($_SESSION['user'])) {
            return $this->unauthorized($request, 'No autenticado');
        }

        $user = $_SESSION['user'];

        // Si se especificaron roles, verificar
        if ($this->roles !== null && !in_array($user['role'], $this->roles, true)) {
            return $this->forbidden($request, 'No tienes permisos para acceder a este recurso');
        }

        // Guardar usuario en el request para uso posterior
        $request->setAttribute('user', $user);

        return $next($request);
    }

    /**
     * Respuesta 401 Unauthorized
     * 
     * @param Request $request
     * @param string $message
     * @return Response
     */
    private function unauthorized(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            return Response::error($message, 'UNAUTHORIZED', null, 401);
        }

        // Redirigir a login
        $baseUrl = $this->getBaseUrl();
        return Response::redirect($baseUrl . '/public/login.php');
    }

    /**
     * Respuesta 403 Forbidden
     * 
     * @param Request $request
     * @param string $message
     * @return Response
     */
    private function forbidden(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            return Response::error($message, 'FORBIDDEN', null, 403);
        }

        // Página de acceso denegado
        $baseUrl = $this->getBaseUrl();
        return Response::redirect($baseUrl . '/public/access_denied.php');
    }

    /**
     * Obtiene base URL
     * 
     * @return string
     */
    private function getBaseUrl(): string
    {
        if (defined('BASE_URL')) {
            return BASE_URL;
        }

        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        return "$protocol://$host";
    }
}









