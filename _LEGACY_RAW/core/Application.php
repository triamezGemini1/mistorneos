<?php



namespace Core;

use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Router;
use Core\Container\Container;

/**
 * Aplicación principal - Bootstrap del sistema
 * 
 * Responsabilidades:
 * - Inicialización del contenedor DI
 * - Configuración del entorno
 * - Pipeline de middleware
 * - Routing y dispatch de controladores
 * 
 * @package Core
 * @version 1.0.0
 */
final class Application
{
    private Container $container;
    private Router $router;
    private array $middleware = [];
    private array $config;
    private static ?self $instance = null;

    /**
     * Constructor privado (Singleton pattern)
     * 
     * @param string $basePath Ruta base de la aplicación
     */
    private function __construct(private string $basePath)
    {
        $this->container = new Container();
        $this->router = new Router($this->container);
        $this->loadConfiguration();
        $this->registerCoreServices();
    }

    /**
     * Obtiene la instancia única de la aplicación
     * 
     * @param string|null $basePath Ruta base (solo primera vez)
     * @return self
     */
    public static function getInstance(?string $basePath = null): self
    {
        if (self::$instance === null) {
            if ($basePath === null) {
                throw new \RuntimeException('Base path required for first instantiation');
            }
            self::$instance = new self($basePath);
        }
        
        return self::$instance;
    }

    /**
     * Carga la configuración desde archivos
     * 
     * @return void
     */
    private function loadConfiguration(): void
    {
        $configPath = $this->basePath . '/config';
        
        $this->config = [
            'app' => require $configPath . '/app.php',
            'database' => require $configPath . '/database.php',
            'security' => require $configPath . '/security.php',
            'cache' => require $configPath . '/cache.php',
        ];

        // Cargar variables de entorno si existe .env
        $envFile = $this->basePath . '/.env';
        if (file_exists($envFile)) {
            $this->loadEnvironmentVariables($envFile);
        }
    }

    /**
     * Carga variables de entorno desde archivo .env
     * 
     * @param string $file Ruta al archivo .env
     * @return void
     */
    private function loadEnvironmentVariables(string $file): void
    {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Ignorar comentarios
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parse KEY=VALUE
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remover comillas
                $value = trim($value, '"\'');
                
                // Setear en $_ENV y putenv()
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }

    /**
     * Registra servicios core en el contenedor DI
     * 
     * @return void
     */
    private function registerCoreServices(): void
    {
        // Request
        $this->container->singleton(Request::class, function() {
            return Request::capture();
        });

        // Router
        $this->container->singleton(Router::class, function() {
            return $this->router;
        });

        // Database (se implementará después)
        // Cache (se implementará después)
        // Logger (se implementará después)
    }

    /**
     * Registra middleware global
     * 
     * @param string $middlewareClass Clase del middleware
     * @return self
     */
    public function addMiddleware(string $middlewareClass): self
    {
        $this->middleware[] = $middlewareClass;
        return $this;
    }

    /**
     * Obtiene el router
     * 
     * @return Router
     */
    public function router(): Router
    {
        return $this->router;
    }

    /**
     * Obtiene el contenedor DI
     * 
     * @return Container
     */
    public function container(): Container
    {
        return $this->container;
    }

    /**
     * Obtiene configuración
     * 
     * @param string|null $key Clave dot-notation (ej: 'app.name')
     * @param mixed $default Valor por defecto
     * @return mixed
     */
    public function config(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->config;
        }

        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $segment) {
            if (!isset($value[$segment])) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Ejecuta la aplicación
     * 
     * @return void
     */
    public function run(): void
    {
        try {
            // Capturar request
            $request = $this->container->make(Request::class);

            // Ejecutar middleware pipeline
            $response = $this->runMiddlewarePipeline($request, function($request) {
                // Dispatch del router
                return $this->router->dispatch($request);
            });

            // Enviar respuesta
            $response->send();

        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * Ejecuta el pipeline de middleware
     * 
     * @param Request $request
     * @param callable $coreHandler Handler final (router dispatch)
     * @return Response
     */
    private function runMiddlewarePipeline(Request $request, callable $coreHandler): Response
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            function ($next, $middleware) {
                return function ($request) use ($next, $middleware) {
                    $instance = $this->container->make($middleware);
                    return $instance->handle($request, $next);
                };
            },
            $coreHandler
        );

        return $pipeline($request);
    }

    /**
     * Maneja excepciones no capturadas
     * 
     * @param \Throwable $e
     * @return void
     */
    private function handleException(\Throwable $e): void
    {
        // Log del error
        error_log(sprintf(
            "[%s] %s in %s:%d\nStack trace:\n%s",
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        ));

        // Respuesta según entorno
        $debug = $this->config('app.debug', false);

        if ($debug) {
            // Modo debug: mostrar detalles del error
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => [
                    'type' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => explode("\n", $e->getTraceAsString())
                ]
            ], JSON_PRETTY_PRINT);
        } else {
            // Modo producción: mensaje genérico
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => [
                    'message' => 'Ha ocurrido un error interno. Por favor contacte al administrador.'
                ]
            ]);
        }

        exit(1);
    }

    /**
     * Obtiene la ruta base de la aplicación
     * 
     * @return string
     */
    public function basePath(): string
    {
        return $this->basePath;
    }

    /**
     * Helper para obtener ruta completa
     * 
     * @param string $path Ruta relativa
     * @return string
     */
    public function path(string $path = ''): string
    {
        return $this->basePath . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : '');
    }
}









