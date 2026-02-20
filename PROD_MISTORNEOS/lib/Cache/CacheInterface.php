<?php



namespace Lib\Cache;

/**
 * Cache Interface - Contract para implementaciones de caché
 * 
 * Compatible con PSR-16 Simple Cache
 * 
 * @package Lib\Cache
 * @version 1.0.0
 */
interface CacheInterface
{
    /**
     * Obtiene un valor del caché
     * 
     * @param string $key
     * @param mixed $default Valor por defecto si no existe
     * @return mixed
     */
    public function get(string $key, $default = null);

    /**
     * Establece un valor en el caché
     * 
     * @param string $key
     * @param mixed $value
     * @param int|null $ttl Tiempo de vida en segundos (null = forever)
     * @return bool
     */
    public function set(string $key, $value, ?int $ttl = null): bool;

    /**
     * Verifica si existe una key en el caché
     * 
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Elimina un valor del caché
     * 
     * @param string $key
     * @return bool
     */
    public function delete(string $key): bool;

    /**
     * Limpia todo el caché
     * 
     * @return bool
     */
    public function clear(): bool;

    /**
     * Obtiene múltiples valores
     * 
     * @param array $keys
     * @param mixed $default
     * @return array
     */
    public function getMultiple(array $keys, $default = null): array;

    /**
     * Establece múltiples valores
     * 
     * @param array $values ['key' => 'value', ...]
     * @param int|null $ttl
     * @return bool
     */
    public function setMultiple(array $values, ?int $ttl = null): bool;

    /**
     * Elimina múltiples valores
     * 
     * @param array $keys
     * @return bool
     */
    public function deleteMultiple(array $keys): bool;

    /**
     * Incrementa un valor numérico
     * 
     * @param string $key
     * @param int $value
     * @return int|false Nuevo valor o false si no existe
     */
    public function increment(string $key, int $value = 1);

    /**
     * Decrementa un valor numérico
     * 
     * @param string $key
     * @param int $value
     * @return int|false Nuevo valor o false si no existe
     */
    public function decrement(string $key, int $value = 1);

    /**
     * Remember: Obtiene de caché o ejecuta callback y guarda
     * 
     * @param string $key
     * @param int|null $ttl
     * @param callable $callback
     * @return mixed
     */
    public function remember(string $key, ?int $ttl, callable $callback);
}









