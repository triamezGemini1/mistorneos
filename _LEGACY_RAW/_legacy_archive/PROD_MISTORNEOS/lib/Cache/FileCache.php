<?php



namespace Lib\Cache;

/**
 * File Cache - Implementación de caché en archivos
 * 
 * Características:
 * - Serialización automática
 * - TTL per-item
 * - Limpieza automática de expirados
 * - Thread-safe con file locks
 * 
 * @package Lib\Cache
 * @version 1.0.0
 */
class FileCache implements CacheInterface
{
    private string $cachePath;
    private int $defaultTtl;
    private string $prefix;

    /**
     * Constructor
     * 
     * @param string $cachePath Directorio de caché
     * @param int $defaultTtl TTL por defecto en segundos
     * @param string $prefix Prefijo para keys
     */
    public function __construct(
        string $cachePath,
        int $defaultTtl = 3600,
        string $prefix = ''
    ) {
        $this->cachePath = rtrim($cachePath, '/');
        $this->defaultTtl = $defaultTtl;
        $this->prefix = $prefix;

        // Crear directorio si no existe
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, $default = null)
    {
        $filepath = $this->getFilePath($key);

        if (!file_exists($filepath)) {
            return $default;
        }

        $content = file_get_contents($filepath);
        if ($content === false) {
            return $default;
        }

        $data = unserialize($content);

        // Verificar expiración
        if ($data['expires_at'] !== null && $data['expires_at'] < time()) {
            $this->delete($key);
            return $default;
        }

        return $data['value'];
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, $value, ?int $ttl = null): bool
    {
        $filepath = $this->getFilePath($key);
        $ttl = $ttl ?? $this->defaultTtl;

        $data = [
            'value' => $value,
            'expires_at' => $ttl > 0 ? time() + $ttl : null,
            'created_at' => time()
        ];

        $content = serialize($data);
        
        $result = file_put_contents($filepath, $content, LOCK_EX);

        if ($result !== false) {
            chmod($filepath, 0644);
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        return $this->get($key, null) !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        $filepath = $this->getFilePath($key);

        if (file_exists($filepath)) {
            return unlink($filepath);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        $files = glob($this->cachePath . '/*');
        
        if ($files === false) {
            return false;
        }

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple(array $keys, $default = null): array
    {
        $results = [];

        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        $success = true;

        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(array $keys): bool
    {
        $success = true;

        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function increment(string $key, int $value = 1)
    {
        $current = $this->get($key, 0);

        if (!is_numeric($current)) {
            return false;
        }

        $new = (int)$current + $value;
        $this->set($key, $new);

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function decrement(string $key, int $value = 1)
    {
        return $this->increment($key, -$value);
    }

    /**
     * {@inheritdoc}
     */
    public function remember(string $key, ?int $ttl, callable $callback)
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Obtiene ruta del archivo de caché
     * 
     * @param string $key
     * @return string
     */
    private function getFilePath(string $key): string
    {
        $hashedKey = md5($this->prefix . $key);
        return $this->cachePath . '/' . $hashedKey . '.cache';
    }

    /**
     * Limpia archivos expirados (garbage collection)
     * 
     * @return int Número de archivos eliminados
     */
    public function gc(): int
    {
        $files = glob($this->cachePath . '/*.cache');
        
        if ($files === false) {
            return 0;
        }

        $deleted = 0;

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            $data = unserialize($content);

            if ($data['expires_at'] !== null && $data['expires_at'] < time()) {
                unlink($file);
                $deleted++;
            }
        }

        return $deleted;
    }
}









