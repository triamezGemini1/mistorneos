<?php



namespace Core\Routing;

use Core\Container\Container;
use Core\Http\Request;
use Core\Http\Response;

/**
 * Router - Sistema de enrutamiento HTTP
 * 
 * Soporta:
 * - Métodos HTTP (GET, POST, PUT, PATCH, DELETE)
 * - Parámetros dinámicos en URLs ({id}, {slug})
 * - Grupos de rutas con prefijos
 * - Middleware por ruta o grupo
 * - Named routes
 * 
 * @package Core\Routing
 * @version 1.0.0
 */
class Router
{
    /**
     * Rutas registradas por método HTTP
     * @var array<string, array>
     */
    private array $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'PATCH' => [],
        'DELETE' => [],
    ];

    /**
     * Rutas nombradas
     * @var array<string, string>
     */
    private array $namedRoutes = [];

    /**
     * Prefijo actual de grupo
     * @var string
     */
    private string $groupPrefix = '';

    /**
     * Middleware actual de grupo
     * @var array
     */
    private array $groupMiddleware = [];

    /**
     * Constructor
     * 
     * @param Container $container
     */
    public function __construct(private Container $container)
    {
    }

    /**
     * Registra ruta GET
     * 
     * @param string $uri
     * @param callable|array|string $action
     * @return Route
     */
    public function get(string $uri, $action): Route
    {
        return $this->addRoute('GET', $uri, $action);
    }

    /**
     * Registra ruta POST
     * 
     * @param string $uri
     * @param callable|array|string $action
     * @return Route
     */
    public function post(string $uri, $action): Route
    {
        return $this->addRoute('POST', $uri, $action);
    }

    /**
     * Registra ruta PUT
     * 
     * @param string $uri
     * @param callable|array|string $action
     * @return Route
     */
    public function put(string $uri, $action): Route
    {
        return $this->addRoute('PUT', $uri, $action);
    }

    /**
     * Registra ruta PATCH
     * 
     * @param string $uri
     * @param callable|array|string $action
     * @return Route
     */
    public function patch(string $uri, $action): Route
    {
        return $this->addRoute('PATCH', $uri, $action);
    }

    /**
     * Registra ruta DELETE
     * 
     * @param string $uri
     * @param callable|array|string $action
     * @return Route
     */
    public function delete(string $uri, $action): Route
    {
        return $this->addRoute('DELETE', $uri, $action);
    }

    /**
     * Registra ruta para múltiples métodos
     * 
     * @param array $methods
     * @param string $uri
     * @param callable|array|string $action
     * @return Route
     */
    public function match(array $methods, string $uri, $action): Route
    {
        $route = null;
        foreach ($methods as $method) {
            $route = $this->addRoute(strtoupper($method), $uri, $action);
        }
        return $route;
    }

    /**
     * Registra ruta para todos los métodos
     * 
     * @param string $uri
     * @param callable|array|string $action
     * @return Route
     */
    public function any(string $uri, $action): Route
    {
        return $this->match(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], $uri, $action);
    }

    /**
     * Agregar ruta al registro
     * 
     * @param string $method
     * @param string $uri
     * @param callable|array|string $action
     * @return Route
     */
    private function addRoute(string $method, string $uri, $action): Route
    {
        $uri = $this->groupPrefix . '/' . trim($uri, '/');
        $uri = '/' . trim($uri, '/') ?: '/';

        $route = new Route($method, $uri, $action);
        
        // Aplicar middleware de grupo
        if (!empty($this->groupMiddleware)) {
            $route->middleware($this->groupMiddleware);
        }

        $this->routes[$method][] = $route;

        return $route;
    }

    /**
     * Crea un grupo de rutas con prefijo y/o middleware
     * 
     * @param array $attributes ['prefix' => '/admin', 'middleware' => [AuthMiddleware::class]]
     * @param callable $callback
     * @return void
     */
    public function group(array $attributes, callable $callback): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        if (isset($attributes['prefix'])) {
            $this->groupPrefix .= '/' . trim($attributes['prefix'], '/');
        }

        if (isset($attributes['middleware'])) {
            $middleware = is_array($attributes['middleware']) ? $attributes['middleware'] : [$attributes['middleware']];
            $this->groupMiddleware = array_merge($this->groupMiddleware, $middleware);
        }

        $callback($this);

        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    /**
     * Despacha una solicitud a la ruta correspondiente
     * 
     * @param Request $request
     * @return Response
     */
    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $uri = $request->uri();

        // Buscar ruta que coincida
        foreach ($this->routes[$method] ?? [] as $route) {
            if ($route->matches($uri)) {
                // Extraer parámetros de la URL
                $params = $route->extractParameters($uri);
                
                // Guardar parámetros en el request
                foreach ($params as $key => $value) {
                    $request->setAttribute($key, $value);
                }

                // Ejecutar middleware de la ruta
                return $this->runRouteMiddleware($route, $request, function($request) use ($route, $params) {
                    return $this->callAction($route->getAction(), $params);
                });
            }
        }

        // No se encontró ruta
        return Response::error('Ruta no encontrada', 'NOT_FOUND', null, 404);
    }

    /**
     * Ejecuta middleware de una ruta específica
     * 
     * @param Route $route
     * @param Request $request
     * @param callable $coreHandler
     * @return Response
     */
    private function runRouteMiddleware(Route $route, Request $request, callable $coreHandler): Response
    {
        $middleware = $route->getMiddleware();

        if (empty($middleware)) {
            return $coreHandler($request);
        }

        $pipeline = array_reduce(
            array_reverse($middleware),
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
     * Llama a la acción del controlador
     * 
     * @param callable|array|string $action
     * @param array $params
     * @return Response
     */
    private function callAction($action, array $params = []): Response
    {
        // Si es un Closure
        if ($action instanceof \Closure) {
            $result = $this->container->call($action, $params);
            return $this->prepareResponse($result);
        }

        // Si es un array [Controller::class, 'method']
        if (is_array($action)) {
            [$controller, $method] = $action;
            $controllerInstance = $this->container->make($controller);
            $result = $this->container->call([$controllerInstance, $method], $params);
            return $this->prepareResponse($result);
        }

        // Si es string "Controller@method"
        if (is_string($action) && strpos($action, '@') !== false) {
            [$controller, $method] = explode('@', $action, 2);
            $controllerInstance = $this->container->make($controller);
            $result = $this->container->call([$controllerInstance, $method], $params);
            return $this->prepareResponse($result);
        }

        return Response::error('Acción de ruta inválida', 'INVALID_ACTION', null, 500);
    }

    /**
     * Prepara la respuesta del controlador
     * 
     * @param mixed $result
     * @return Response
     */
    private function prepareResponse($result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        if (is_array($result) || is_object($result)) {
            return Response::json($result);
        }

        if (is_string($result)) {
            return Response::html($result);
        }

        return new Response((string)$result);
    }

    /**
     * Genera URL para ruta nombrada
     * 
     * @param string $name
     * @param array $params
     * @return string
     */
    public function route(string $name, array $params = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new \RuntimeException("Named route [$name] not found");
        }

        $uri = $this->namedRoutes[$name];

        // Reemplazar parámetros
        foreach ($params as $key => $value) {
            $uri = str_replace('{' . $key . '}', $value, $uri);
        }

        return $uri;
    }

    /**
     * Registra ruta nombrada
     * 
     * @param string $name
     * @param string $uri
     * @return void
     */
    public function nameRoute(string $name, string $uri): void
    {
        $this->namedRoutes[$name] = $uri;
    }
}

/**
 * Clase Route - Representa una ruta individual
 */
class Route
{
    private array $middleware = [];
    private ?string $name = null;

    /**
     * Constructor
     * 
     * @param string $method
     * @param string $uri
     * @param callable|array|string $action
     */
    public function __construct(
        private string $method,
        private string $uri,
        private $action
    ) {
    }

    /**
     * Verifica si la URI coincide con el patrón de la ruta
     * 
     * @param string $uri
     * @return bool
     */
    public function matches(string $uri): bool
    {
        $pattern = $this->getPattern();
        return preg_match($pattern, $uri) === 1;
    }

    /**
     * Convierte URI con parámetros a regex pattern
     * 
     * @return string
     */
    private function getPattern(): string
    {
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $this->uri);
        return '#^' . $pattern . '$#';
    }

    /**
     * Extrae parámetros de la URI
     * 
     * @param string $uri
     * @return array
     */
    public function extractParameters(string $uri): array
    {
        $pattern = $this->getPattern();
        
        if (preg_match($pattern, $uri, $matches)) {
            // Filtrar solo las claves nombradas
            return array_filter($matches, function($key) {
                return !is_numeric($key);
            }, ARRAY_FILTER_USE_KEY);
        }

        return [];
    }

    /**
     * Agrega middleware a la ruta
     * 
     * @param array|string $middleware
     * @return self
     */
    public function middleware($middleware): self
    {
        $middleware = is_array($middleware) ? $middleware : [$middleware];
        $this->middleware = array_merge($this->middleware, $middleware);
        return $this;
    }

    /**
     * Obtiene middleware de la ruta
     * 
     * @return array
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Asigna nombre a la ruta
     * 
     * @param string $name
     * @return self
     */
    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Obtiene la acción
     * 
     * @return callable|array|string
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * Obtiene URI de la ruta
     * 
     * @return string
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Obtiene método HTTP
     * 
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }
}









