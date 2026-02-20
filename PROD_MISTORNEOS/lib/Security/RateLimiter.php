<?php



namespace Lib\Security;

/**
 * Rate Limiter - Limitador de tasa de peticiones
 * 
 * Implementa algoritmo Token Bucket para limitar requests por IP/usuario
 * Previene:
 * - Brute force attacks
 * - DDoS
 * - Scraping abusivo
 * - API abuse
 * 
 * @package Lib\Security
 * @version 1.0.0
 */
class RateLimiter
{
    private const STORAGE_PREFIX = 'rate_limit_';

    /**
     * Constructor
     * 
     * @param string $storageDriver 'session', 'file', 'redis', 'memcached'
     * @param string|null $storagePath Ruta para file storage
     */
    public function __construct(
        private string $storageDriver = 'session',
        private ?string $storagePath = null
    ) {
        if ($this->storageDriver === 'file' && $this->storagePath === null) {
            $this->storagePath = sys_get_temp_dir();
        }
    }

    /**
     * Intenta ejecutar acción respetando límite
     * 
     * @param string $key Identificador único (IP, user_id, etc.)
     * @param int $maxAttempts Máximo de intentos permitidos
     * @param int $decaySeconds Ventana de tiempo en segundos
     * @return bool True si se permite, false si excede límite
     */
    public function attempt(string $key, int $maxAttempts = 60, int $decaySeconds = 60): bool
    {
        $storageKey = $this->getStorageKey($key);
        $data = $this->getData($storageKey);

        $now = time();

        // Si no existe data o ha expirado, inicializar
        if ($data === null || $data['reset_at'] < $now) {
            $this->setData($storageKey, [
                'attempts' => 1,
                'reset_at' => $now + $decaySeconds
            ]);
            return true;
        }

        // Si ya excedió el límite
        if ($data['attempts'] >= $maxAttempts) {
            return false;
        }

        // Incrementar contador
        $data['attempts']++;
        $this->setData($storageKey, $data);

        return true;
    }

    /**
     * Verifica si se excedió el límite SIN incrementar contador
     * 
     * @param string $key
     * @param int $maxAttempts
     * @return bool True si aún puede intentar
     */
    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        $storageKey = $this->getStorageKey($key);
        $data = $this->getData($storageKey);

        if ($data === null) {
            return false;
        }

        $now = time();

        // Si ha expirado, puede intentar
        if ($data['reset_at'] < $now) {
            return false;
        }

        return $data['attempts'] >= $maxAttempts;
    }

    /**
     * Obtiene intentos restantes
     * 
     * @param string $key
     * @param int $maxAttempts
     * @return int
     */
    public function remaining(string $key, int $maxAttempts): int
    {
        $storageKey = $this->getStorageKey($key);
        $data = $this->getData($storageKey);

        if ($data === null) {
            return $maxAttempts;
        }

        $now = time();

        // Si ha expirado
        if ($data['reset_at'] < $now) {
            return $maxAttempts;
        }

        $remaining = $maxAttempts - $data['attempts'];
        return max(0, $remaining);
    }

    /**
     * Obtiene segundos hasta el reset
     * 
     * @param string $key
     * @return int
     */
    public function availableIn(string $key): int
    {
        $storageKey = $this->getStorageKey($key);
        $data = $this->getData($storageKey);

        if ($data === null) {
            return 0;
        }

        $now = time();
        $availableIn = $data['reset_at'] - $now;

        return max(0, $availableIn);
    }

    /**
     * Limpia/resetea el límite para una key
     * 
     * @param string $key
     * @return void
     */
    public function clear(string $key): void
    {
        $storageKey = $this->getStorageKey($key);
        $this->deleteData($storageKey);
    }

    /**
     * Incrementa intentos fallidos
     * 
     * @param string $key
     * @param int $decaySeconds
     * @return int Total de intentos
     */
    public function hit(string $key, int $decaySeconds = 60): int
    {
        $storageKey = $this->getStorageKey($key);
        $data = $this->getData($storageKey);

        $now = time();

        // Si no existe o ha expirado
        if ($data === null || $data['reset_at'] < $now) {
            $this->setData($storageKey, [
                'attempts' => 1,
                'reset_at' => $now + $decaySeconds
            ]);
            return 1;
        }

        $data['attempts']++;
        $this->setData($storageKey, $data);

        return $data['attempts'];
    }

    /**
     * Genera clave de storage
     * 
     * @param string $key
     * @return string
     */
    private function getStorageKey(string $key): string
    {
        return self::STORAGE_PREFIX . hash('sha256', $key);
    }

    /**
     * Obtiene datos del storage
     * 
     * @param string $key
     * @return array|null
     */
    private function getData(string $key): ?array
    {
        switch ($this->storageDriver) {
            case 'session':
                return $this->getFromSession($key);
            case 'file':
                return $this->getFromFile($key);
            // case 'redis': return $this->getFromRedis($key);
            // case 'memcached': return $this->getFromMemcached($key);
            default:
                return $this->getFromSession($key);
        }
    }

    /**
     * Guarda datos en storage
     * 
     * @param string $key
     * @param array $data
     * @return void
     */
    private function setData(string $key, array $data): void
    {
        switch ($this->storageDriver) {
            case 'session':
                $this->setToSession($key, $data);
                break;
            case 'file':
                $this->setToFile($key, $data);
                break;
            // case 'redis': $this->setToRedis($key, $data); break;
            // case 'memcached': $this->setToMemcached($key, $data); break;
            default:
                $this->setToSession($key, $data);
        }
    }

    /**
     * Elimina datos del storage
     * 
     * @param string $key
     * @return void
     */
    private function deleteData(string $key): void
    {
        switch ($this->storageDriver) {
            case 'session':
                if (session_status() === PHP_SESSION_ACTIVE) {
                    unset($_SESSION[$key]);
                }
                break;
            case 'file':
                $filePath = $this->storagePath . '/' . $key;
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                break;
        }
    }

    /**
     * Storage: Session
     */
    private function getFromSession(string $key): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        return $_SESSION[$key] ?? null;
    }

    private function setToSession(string $key, array $data): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION[$key] = $data;
    }

    /**
     * Storage: File
     */
    private function getFromFile(string $key): ?array
    {
        $filePath = $this->storagePath . '/' . $key;
        
        if (!file_exists($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);
        $data = json_decode($content, true);

        return $data ?: null;
    }

    private function setToFile(string $key, array $data): void
    {
        $filePath = $this->storagePath . '/' . $key;
        file_put_contents($filePath, json_encode($data), LOCK_EX);
    }

    /**
     * Helper: Obtener IP del cliente
     * 
     * @return string
     */
    public static function getClientIp(): string
    {
        $keys = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }

    /**
     * Helper: Generar key para login throttling
     * 
     * @param string $username
     * @return string
     */
    public static function loginKey(string $username): string
    {
        return 'login_' . $username . '_' . self::getClientIp();
    }
}









