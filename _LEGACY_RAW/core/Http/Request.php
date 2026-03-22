<?php



namespace Core\Http;

/**
 * HTTP Request - Abstracción de la solicitud HTTP
 * 
 * Encapsula $_GET, $_POST, $_SERVER, $_FILES, $_COOKIE
 * con métodos seguros y convenientes
 * 
 * @package Core\Http
 * @version 1.0.0
 */
class Request
{
    private array $query;
    private array $request;
    private array $attributes;
    private array $cookies;
    private array $files;
    private array $server;
    private ?string $content;
    private array $headers;

    /**
     * Constructor
     * 
     * @param array $query $_GET
     * @param array $request $_POST
     * @param array $attributes Atributos personalizados
     * @param array $cookies $_COOKIE
     * @param array $files $_FILES
     * @param array $server $_SERVER
     * @param string|null $content Body content
     */
    public function __construct(
        array $query = [],
        array $request = [],
        array $attributes = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        ?string $content = null
    ) {
        $this->query = $query;
        $this->request = $request;
        $this->attributes = $attributes;
        $this->cookies = $cookies;
        $this->files = $files;
        $this->server = $server;
        $this->content = $content;
        $this->headers = $this->extractHeaders($server);
    }

    /**
     * Crea Request desde globals PHP
     * 
     * @return self
     */
    public static function capture(): self
    {
        return new self(
            $_GET,
            $_POST,
            [],
            $_COOKIE,
            $_FILES,
            $_SERVER,
            file_get_contents('php://input')
        );
    }

    /**
     * Extrae headers de $_SERVER
     * 
     * @param array $server
     * @return array
     */
    private function extractHeaders(array $server): array
    {
        $headers = [];
        
        foreach ($server as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headerName = str_replace('_', '-', substr($key, 5));
                $headers[$headerName] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'])) {
                $headerName = str_replace('_', '-', $key);
                $headers[$headerName] = $value;
            }
        }
        
        return $headers;
    }

    /**
     * Obtiene parámetro del query string ($_GET)
     * 
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function query(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->query;
        }
        
        return $this->query[$key] ?? $default;
    }

    /**
     * Obtiene parámetro del body ($_POST)
     * 
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function post(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->request;
        }
        
        return $this->request[$key] ?? $default;
    }

    /**
     * Obtiene parámetro de cualquier fuente (POST > GET)
     * 
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function input(?string $key = null, $default = null)
    {
        if ($key === null) {
            return array_merge($this->query, $this->request);
        }
        
        return $this->request[$key] ?? $this->query[$key] ?? $default;
    }

    /**
     * Obtiene todos los inputs
     * 
     * @return array
     */
    public function all(): array
    {
        return array_merge($this->query, $this->request);
    }

    /**
     * Obtiene solo los inputs especificados
     * 
     * @param array $keys
     * @return array
     */
    public function only(array $keys): array
    {
        $results = [];
        $input = $this->all();
        
        foreach ($keys as $key) {
            if (array_key_exists($key, $input)) {
                $results[$key] = $input[$key];
            }
        }
        
        return $results;
    }

    /**
     * Obtiene todos menos los especificados
     * 
     * @param array $keys
     * @return array
     */
    public function except(array $keys): array
    {
        $results = $this->all();
        
        foreach ($keys as $key) {
            unset($results[$key]);
        }
        
        return $results;
    }

    /**
     * Verifica si existe un input
     * 
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    /**
     * Verifica si existe y no está vacío
     * 
     * @param string $key
     * @return bool
     */
    public function filled(string $key): bool
    {
        $value = $this->input($key);
        return !empty($value);
    }

    /**
     * Obtiene header
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function header(string $key, $default = null)
    {
        $key = strtoupper(str_replace('-', '_', $key));
        return $this->headers[$key] ?? $default;
    }

    /**
     * Obtiene todos los headers
     * 
     * @return array
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Obtiene método HTTP
     * 
     * @return string
     */
    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * Verifica si es método específico
     * 
     * @param string $method
     * @return bool
     */
    public function isMethod(string $method): bool
    {
        return $this->method() === strtoupper($method);
    }

    /**
     * Obtiene URI (sin query string)
     * 
     * @return string
     */
    public function uri(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        
        // Remover query string
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }
        
        return $uri;
    }

    /**
     * Obtiene URL completa
     * 
     * @return string
     */
    public function url(): string
    {
        $scheme = $this->isSecure() ? 'https' : 'http';
        $host = $this->host();
        $uri = $this->uri();
        
        return "$scheme://$host$uri";
    }

    /**
     * Obtiene host
     * 
     * @return string
     */
    public function host(): string
    {
        return $this->server['HTTP_HOST'] ?? $this->server['SERVER_NAME'] ?? 'localhost';
    }

    /**
     * Verifica si es HTTPS
     * 
     * @return bool
     */
    public function isSecure(): bool
    {
        return (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off')
            || $this->server['SERVER_PORT'] === 443
            || $this->server['HTTP_X_FORWARDED_PROTO'] === 'https';
    }

    /**
     * Verifica si es AJAX
     * 
     * @return bool
     */
    public function isAjax(): bool
    {
        return $this->header('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * Verifica si es JSON request
     * 
     * @return bool
     */
    public function isJson(): bool
    {
        return strpos($this->header('Content-Type', ''), 'application/json') !== false;
    }

    /**
     * Verifica si espera JSON response
     * 
     * @return bool
     */
    public function expectsJson(): bool
    {
        return $this->isJson() || $this->isAjax();
    }

    /**
     * Obtiene IP del cliente
     * 
     * @return string
     */
    public function ip(): string
    {
        // Orden de prioridad para proxies
        $keys = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($keys as $key) {
            if (!empty($this->server[$key])) {
                $ip = $this->server[$key];
                
                // X-Forwarded-For puede contener múltiples IPs
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                
                // Validar IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }

    /**
     * Obtiene User-Agent
     * 
     * @return string
     */
    public function userAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * Obtiene archivo subido
     * 
     * @param string $key
     * @return array|null
     */
    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    /**
     * Verifica si tiene archivo
     * 
     * @param string $key
     * @return bool
     */
    public function hasFile(string $key): bool
    {
        $file = $this->file($key);
        return $file && isset($file['error']) && $file['error'] === UPLOAD_ERR_OK;
    }

    /**
     * Obtiene contenido raw del body
     * 
     * @return string|null
     */
    public function getContent(): ?string
    {
        return $this->content;
    }

    /**
     * Parsea body como JSON
     * 
     * @param bool $assoc Retornar array asociativo
     * @return mixed
     */
    public function json(bool $assoc = true)
    {
        if ($this->content === null) {
            return $assoc ? [] : null;
        }
        
        return json_decode($this->content, $assoc);
    }

    /**
     * Obtiene bearer token del header Authorization
     * 
     * @return string|null
     */
    public function bearerToken(): ?string
    {
        $header = $this->header('Authorization');
        
        if ($header && preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Establece atributo personalizado
     * 
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function setAttribute(string $key, $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Obtiene atributo personalizado
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getAttribute(string $key, $default = null)
    {
        return $this->attributes[$key] ?? $default;
    }
}









