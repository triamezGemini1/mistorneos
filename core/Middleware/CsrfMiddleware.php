<?php



namespace Core\Middleware;

use Core\Http\Request;
use Core\Http\Response;
use Lib\Security\Csrf;

/**
 * CSRF Middleware - Valida tokens CSRF en requests mutables
 * 
 * @package Core\Middleware
 * @version 1.0.0
 */
class CsrfMiddleware implements Middleware
{
    /**
     * Métodos HTTP que requieren validación CSRF
     */
    private const PROTECTED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * URIs exceptuadas de validación CSRF (ej: webhooks, APIs públicas)
     * @var array
     */
    private array $except = [];

    /**
     * Constructor
     * 
     * @param array $except URIs a exceptuar
     */
    public function __construct(array $except = [])
    {
        $this->except = $except;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Request $request, callable $next): Response
    {
        // Verificar si el método requiere protección CSRF
        if (!in_array($request->method(), self::PROTECTED_METHODS)) {
            return $next($request);
        }

        // Verificar si la URI está exceptuada
        if ($this->isExcepted($request->uri())) {
            return $next($request);
        }

        // Validar token CSRF
        if (!Csrf::validateFromRequest()) {
            if ($request->expectsJson()) {
                return Response::error(
                    'Token CSRF inválido o expirado',
                    'CSRF_TOKEN_MISMATCH',
                    null,
                    419
                );
            }

            // Para requests HTML, redirigir con error
            return Response::html(
                $this->renderErrorPage(),
                419
            );
        }

        return $next($request);
    }

    /**
     * Verifica si una URI está exceptuada
     * 
     * @param string $uri
     * @return bool
     */
    private function isExcepted(string $uri): bool
    {
        foreach ($this->except as $pattern) {
            if ($this->matchesPattern($uri, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifica si URI coincide con patrón
     * 
     * @param string $uri
     * @param string $pattern
     * @return bool
     */
    private function matchesPattern(string $uri, string $pattern): bool
    {
        // Soporta wildcards: /api/* o /webhook/*
        $pattern = preg_quote($pattern, '#');
        $pattern = str_replace('\*', '.*', $pattern);
        
        return preg_match('#^' . $pattern . '$#', $uri) === 1;
    }

    /**
     * Renderiza página de error CSRF
     * 
     * @return string
     */
    private function renderErrorPage(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error de Seguridad - 419</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .error-container {
            text-align: center;
            background: white;
            padding: 3rem 2rem;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
        }
        .error-code {
            font-size: 6rem;
            font-weight: 700;
            color: #667eea;
            margin: 0;
            line-height: 1;
        }
        .error-title {
            font-size: 1.5rem;
            color: #2d3748;
            margin: 1rem 0 0.5rem;
        }
        .error-message {
            color: #718096;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        .btn {
            display: inline-block;
            padding: 0.75rem 2rem;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #5568d3;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <h1 class="error-code">419</h1>
        <h2 class="error-title">Token CSRF Inválido</h2>
        <p class="error-message">
            Tu sesión ha expirado o el token de seguridad es inválido. 
            Por favor, recarga la página e intenta nuevamente.
        </p>
        <a href="javascript:history.back()" class="btn">Volver</a>
    </div>
</body>
</html>
HTML;
    }
}









