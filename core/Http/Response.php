<?php



namespace Core\Http;

/**
 * HTTP Response - Abstracción de la respuesta HTTP
 * 
 * Maneja headers, status codes, content types y envío al cliente
 * 
 * @package Core\Http
 * @version 1.0.0
 */
class Response
{
    private string $content;
    private int $statusCode;
    private array $headers = [];
    private static array $statusTexts = [
        200 => 'OK',
        201 => 'Created',
        204 => 'No Content',
        301 => 'Moved Permanently',
        302 => 'Found',
        304 => 'Not Modified',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        503 => 'Service Unavailable',
    ];

    /**
     * Constructor
     * 
     * @param string $content Contenido de la respuesta
     * @param int $statusCode Código de estado HTTP
     * @param array $headers Headers adicionales
     */
    public function __construct(string $content = '', int $statusCode = 200, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    /**
     * Crea respuesta JSON
     * 
     * @param mixed $data Datos a serializar
     * @param int $statusCode
     * @param array $headers
     * @return self
     */
    public static function json($data, int $statusCode = 200, array $headers = []): self
    {
        $headers['Content-Type'] = 'application/json; charset=utf-8';
        
        $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        return new self($content, $statusCode, $headers);
    }

    /**
     * Crea respuesta de éxito JSON
     * 
     * @param mixed $data
     * @param string|null $message
     * @param int $statusCode
     * @return self
     */
    public static function success($data = null, ?string $message = null, int $statusCode = 200): self
    {
        $response = [
            'success' => true,
            'data' => $data,
            'meta' => [
                'timestamp' => date('c'),
            ]
        ];

        if ($message) {
            $response['message'] = $message;
        }

        return self::json($response, $statusCode);
    }

    /**
     * Crea respuesta de error JSON
     * 
     * @param string $message
     * @param string|null $code
     * @param mixed $details
     * @param int $statusCode
     * @return self
     */
    public static function error(
        string $message,
        ?string $code = null,
        $details = null,
        int $statusCode = 400
    ): self {
        $response = [
            'success' => false,
            'error' => [
                'message' => $message,
            ],
            'meta' => [
                'timestamp' => date('c'),
            ]
        ];

        if ($code) {
            $response['error']['code'] = $code;
        }

        if ($details) {
            $response['error']['details'] = $details;
        }

        return self::json($response, $statusCode);
    }

    /**
     * Crea respuesta de redirección
     * 
     * @param string $url
     * @param int $statusCode
     * @return self
     */
    public static function redirect(string $url, int $statusCode = 302): self
    {
        return new self('', $statusCode, ['Location' => $url]);
    }

    /**
     * Crea respuesta de vista HTML
     * 
     * @param string $html
     * @param int $statusCode
     * @return self
     */
    public static function html(string $html, int $statusCode = 200): self
    {
        return new self($html, $statusCode, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * Crea respuesta de archivo para descarga
     * 
     * @param string $path
     * @param string|null $name
     * @return self
     */
    public static function download(string $path, ?string $name = null): self
    {
        if (!file_exists($path)) {
            return self::error('Archivo no encontrado', 'FILE_NOT_FOUND', null, 404);
        }

        $name = $name ?? basename($path);
        $content = file_get_contents($path);
        
        $headers = [
            'Content-Type' => mime_content_type($path) ?: 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
            'Content-Length' => strlen($content),
            'Cache-Control' => 'must-revalidate',
            'Pragma' => 'public',
        ];

        return new self($content, 200, $headers);
    }

    /**
     * Establece header
     * 
     * @param string $key
     * @param string $value
     * @return self
     */
    public function header(string $key, string $value): self
    {
        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * Establece múltiples headers
     * 
     * @param array $headers
     * @return self
     */
    public function withHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    /**
     * Establece status code
     * 
     * @param int $code
     * @return self
     */
    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    /**
     * Establece contenido
     * 
     * @param string $content
     * @return self
     */
    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Obtiene contenido
     * 
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Obtiene status code
     * 
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Obtiene headers
     * 
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Envía la respuesta al cliente
     * 
     * @return void
     */
    public function send(): void
    {
        // Enviar status code
        $this->sendStatusLine();

        // Enviar headers
        $this->sendHeaders();

        // Enviar contenido
        echo $this->content;

        // Flush output buffer
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        } else {
            if (ob_get_level() > 0) {
                ob_end_flush();
            }
            flush();
        }
    }

    /**
     * Envía línea de status
     * 
     * @return void
     */
    private function sendStatusLine(): void
    {
        if (headers_sent()) {
            return;
        }

        $statusText = self::$statusTexts[$this->statusCode] ?? 'Unknown';
        
        $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
        
        header("$protocol $this->statusCode $statusText", true, $this->statusCode);
    }

    /**
     * Envía headers
     * 
     * @return void
     */
    private function sendHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        foreach ($this->headers as $key => $value) {
            header("$key: $value", false);
        }
    }

    /**
     * Convierte a string
     * 
     * @return string
     */
    public function __toString(): string
    {
        return $this->content;
    }
}









