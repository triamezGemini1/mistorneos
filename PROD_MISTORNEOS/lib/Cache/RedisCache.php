<?php



namespace Lib\Cache;

use Redis;
use RedisException;

/**
 * Redis Cache - Implementación de caché con Redis
 * 
 * Requiere extensión PHP Redis
 * 
 * @package Lib\Cache
 * @version 1.0.0
 */
class RedisCache implements CacheInterface
{
    private Redis $redis;
    private int $defaultTtl;
    private string $prefix;

    /**
     * Constructor
     * 
     * @param string $host
     * @param int $port
     * @param int $database
     * @param string|null $password
     * @param int $defaultTtl
     * @param string $prefix
     * @throws RedisException
     */
    public function __construct(
        string $host = '127.0.0.1',
        int $port = 6379,
        int $database = 0,
        ?string $password = null,
        int $defaultTtl = 3600,
        string $prefix = 'cache:'
    ) {
        $this->redis = new Redis();
        $this->redis->connect($host, $port);

        if ($password !== null) {
            $this->redis->auth($password);
        }

        $this->redis->select($database);
        $this->defaultTtl = $defaultTtl;
        $this->prefix = $prefix;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, $default = null)
    {
        $value = $this->redis->get($this->prefixKey($key));

        if ($value === false) {
            return $default;
        }

        return unserialize($value);
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, $value, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $serialized = serialize($value);

        if ($ttl > 0) {
            return $this->redis->setex($this->prefixKey($key), $ttl, $serialized);
        }

        return $this->redis->set($this->prefixKey($key), $serialized);
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        return $this->redis->exists($this->prefixKey($key)) > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        return $this->redis->del($this->prefixKey($key)) > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        // Eliminar solo keys con el prefijo actual
        $keys = $this->redis->keys($this->prefix . '*');
        
        if (empty($keys)) {
            return true;
        }

        return $this->redis->del($keys) > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple(array $keys, $default = null): array
    {
        $prefixedKeys = array_map([$this, 'prefixKey'], $keys);
        $values = $this->redis->mGet($prefixedKeys);

        $results = [];
        foreach ($keys as $i => $key) {
            $results[$key] = $values[$i] !== false ? unserialize($values[$i]) : $default;
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $pipeline = $this->redis->multi(Redis::PIPELINE);

        foreach ($values as $key => $value) {
            $serialized = serialize($value);
            
            if ($ttl > 0) {
                $pipeline->setex($this->prefixKey($key), $ttl, $serialized);
            } else {
                $pipeline->set($this->prefixKey($key), $serialized);
            }
        }

        $results = $pipeline->exec();
        
        return !in_array(false, $results, true);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(array $keys): bool
    {
        $prefixedKeys = array_map([$this, 'prefixKey'], $keys);
        return $this->redis->del($prefixedKeys) > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function increment(string $key, int $value = 1)
    {
        return $this->redis->incrBy($this->prefixKey($key), $value);
    }

    /**
     * {@inheritdoc}
     */
    public function decrement(string $key, int $value = 1)
    {
        return $this->redis->decrBy($this->prefixKey($key), $value);
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
     * Aplica prefijo a la key
     * 
     * @param string $key
     * @return string
     */
    private function prefixKey(string $key): string
    {
        return $this->prefix . $key;
    }

    /**
     * Obtiene conexión Redis (para operaciones avanzadas)
     * 
     * @return Redis
     */
    public function getRedis(): Redis
    {
        return $this->redis;
    }
}









