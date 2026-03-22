<?php



namespace Core\Middleware;

use Core\Http\Request;
use Core\Http\Response;
use Lib\Security\RateLimiter;

/**
 * Rate Limit Middleware - Limita tasa de requests por IP/usuario
 * 
 * @package Core\Middleware
 * @version 1.0.0
 */
class RateLimitMiddleware implements Middleware
{
    private RateLimiter $limiter;

    /**
     * Constructor
     * 
     * @param int $maxAttempts Máximo de requests permitidos
     * @param int $decaySeconds Ventana de tiempo en segundos
     * @param string $storageDriver 'session', 'file', 'redis'
     */
    public function __construct(
        private int $maxAttempts = 60,
        private int $decaySeconds = 60,
        string $storageDriver = 'file'
    ) {
        $storagePath = defined('APP_ROOT') ? APP_ROOT . '/storage/rate_limits' : sys_get_temp_dir();
        
        // Crear directorio si no existe
        if ($storageDriver === 'file' && !is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        $this->limiter = new RateLimiter($storageDriver, $storagePath);
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Request $request, callable $next): Response
    {
        $key = $this->resolveRequestKey($request);

        // Verificar si ya excedió el límite
        if ($this->limiter->tooManyAttempts($key, $this->maxAttempts)) {
            return $this->buildRateLimitResponse($key);
        }

        // Intentar ejecutar
        if (!$this->limiter->attempt($key, $this->maxAttempts, $this->decaySeconds)) {
            return $this->buildRateLimitResponse($key);
        }

        // Procesar request
        $response = $next($request);

        // Agregar headers de rate limit
        return $this->addRateLimitHeaders($response, $key);
    }

    /**
     * Genera key única para el request
     * 
     * @param Request $request
     * @return string
     */
    private function resolveRequestKey(Request $request): string
    {
        $ip = $request->ip();
        $uri = $request->uri();
        
        return "rate_limit:{$ip}:{$uri}";
    }

    /**
     * Construye respuesta de rate limit exceeded
     * 
     * @param string $key
     * @return Response
     */
    private function buildRateLimitResponse(string $key): Response
    {
        $retryAfter = $this->limiter->availableIn($key);

        if ($this->expectsJson()) {
            return Response::error(
                'Demasiadas solicitudes. Por favor espera antes de intentar nuevamente.',
                'TOO_MANY_REQUESTS',
                ['retry_after' => $retryAfter],
                429
            )->header('Retry-After', (string)$retryAfter);
        }

        return Response::html($this->renderRateLimitPage($retryAfter), 429)
            ->header('Retry-After', (string)$retryAfter);
    }

    /**
     * Agrega headers de rate limit a la respuesta
     * 
     * @param Response $response
     * @param string $key
     * @return Response
     */
    private function addRateLimitHeaders(Response $response, string $key): Response
    {
        $remaining = $this->limiter->remaining($key, $this->maxAttempts);
        $retryAfter = $this->limiter->availableIn($key);

        return $response
            ->header('X-RateLimit-Limit', (string)$this->maxAttempts)
            ->header('X-RateLimit-Remaining', (string)$remaining)
            ->header('X-RateLimit-Reset', (string)(time() + $retryAfter));
    }

    /**
     * Verifica si espera respuesta JSON
     * 
     * @return bool
     */
    private function expectsJson(): bool
    {
        return isset($_SERVER['HTTP_ACCEPT']) 
            && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
    }

    /**
     * Renderiza página de rate limit
     * 
     * @param int $retryAfter
     * @return string
     */
    private function renderRateLimitPage(int $retryAfter): string
    {
        $minutes = ceil($retryAfter / 60);
        
        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="{$retryAfter}">
    <title>Demasiadas Solicitudes - 429</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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
            color: #f5576c;
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
        .countdown {
            font-size: 2rem;
            font-weight: 700;
            color: #f5576c;
            margin: 1rem 0;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <h1 class="error-code">429</h1>
        <h2 class="error-title">Demasiadas Solicitudes</h2>
        <p class="error-message">
            Has excedido el límite de solicitudes permitidas. 
            Por favor espera {$minutes} minuto(s) antes de intentar nuevamente.
        </p>
        <div class="countdown" id="countdown">{$retryAfter}s</div>
        <p class="error-message">Esta página se recargará automáticamente.</p>
    </div>
    <script>
        let timeLeft = {$retryAfter};
        const countdown = document.getElementById('countdown');
        const interval = setInterval(() => {
            timeLeft--;
            countdown.textContent = timeLeft + 's';
            if (timeLeft <= 0) {
                clearInterval(interval);
                location.reload();
            }
        }, 1000);
    </script>
</body>
</html>
HTML;
    }
}









