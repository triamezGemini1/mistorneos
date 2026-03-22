<?php



namespace Core\Container;

use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;

/**
 * Dependency Injection Container
 * 
 * Implementa auto-wiring, singleton, binding y resolución automática de dependencias
 * 
 * @package Core\Container
 * @version 1.0.0
 */
class Container
{
    /**
     * Bindings registrados
     * @var array<string, array>
     */
    private array $bindings = [];

    /**
     * Instancias singleton
     * @var array<string, object>
     */
    private array $instances = [];

    /**
     * Aliases de clases
     * @var array<string, string>
     */
    private array $aliases = [];

    /**
     * Registra un binding en el contenedor
     * 
     * @param string $abstract Nombre del servicio (interface o clase)
     * @param Closure|string|null $concrete Implementación
     * @param bool $shared Si es singleton
     * @return void
     */
    public function bind(string $abstract, $concrete = null, bool $shared = false): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => $shared
        ];
    }

    /**
     * Registra un singleton
     * 
     * @param string $abstract
     * @param Closure|string|null $concrete
     * @return void
     */
    public function singleton(string $abstract, $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Registra una instancia existente como singleton
     * 
     * @param string $abstract
     * @param object $instance
     * @return void
     */
    public function instance(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    /**
     * Resuelve un servicio del contenedor
     * 
     * @param string $abstract
     * @param array $parameters Parámetros adicionales
     * @return mixed
     * @throws ContainerException
     */
    public function make(string $abstract, array $parameters = [])
    {
        // Resolver alias
        $abstract = $this->getAlias($abstract);

        // Si ya existe instancia singleton, retornarla
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Obtener concrete del binding
        $concrete = $this->getConcrete($abstract);

        // Construir la instancia
        if ($concrete instanceof Closure) {
            $object = $concrete($this, $parameters);
        } else {
            $object = $this->build($concrete, $parameters);
        }

        // Si es shared (singleton), guardar instancia
        if ($this->isShared($abstract)) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    /**
     * Construye una instancia con auto-wiring
     * 
     * @param string $concrete
     * @param array $parameters
     * @return mixed
     * @throws ContainerException
     */
    private function build(string $concrete, array $parameters = [])
    {
        try {
            $reflector = new ReflectionClass($concrete);
        } catch (ReflectionException $e) {
            throw new ContainerException("Target class [$concrete] does not exist.", 0, $e);
        }

        // Si no es instanciable (interface, abstract)
        if (!$reflector->isInstantiable()) {
            throw new ContainerException("Target [$concrete] is not instantiable.");
        }

        // Obtener constructor
        $constructor = $reflector->getConstructor();

        // Si no tiene constructor, instanciar directamente
        if ($constructor === null) {
            return new $concrete();
        }

        // Resolver dependencias del constructor
        $dependencies = $constructor->getParameters();
        $instances = $this->resolveDependencies($dependencies, $parameters);

        return $reflector->newInstanceArgs($instances);
    }

    /**
     * Resuelve un array de dependencias (parámetros del constructor)
     * 
     * @param ReflectionParameter[] $dependencies
     * @param array $parameters
     * @return array
     * @throws ContainerException
     */
    private function resolveDependencies(array $dependencies, array $parameters): array
    {
        $results = [];

        foreach ($dependencies as $dependency) {
            // Si el parámetro fue proporcionado explícitamente
            if (array_key_exists($dependency->getName(), $parameters)) {
                $results[] = $parameters[$dependency->getName()];
                continue;
            }

            // Si tiene type hint, resolver del contenedor
            $type = $dependency->getType();
            
            if ($type && !$type->isBuiltin()) {
                $results[] = $this->make($type->getName());
                continue;
            }

            // Si tiene valor por defecto, usarlo
            if ($dependency->isDefaultValueAvailable()) {
                $results[] = $dependency->getDefaultValue();
                continue;
            }

            throw new ContainerException(
                "Unable to resolve dependency [{$dependency->getName()}] for class"
            );
        }

        return $results;
    }

    /**
     * Obtiene el concrete de un abstract
     * 
     * @param string $abstract
     * @return mixed
     */
    private function getConcrete(string $abstract)
    {
        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]['concrete'];
        }

        return $abstract;
    }

    /**
     * Verifica si un abstract es compartido (singleton)
     * 
     * @param string $abstract
     * @return bool
     */
    private function isShared(string $abstract): bool
    {
        return isset($this->bindings[$abstract]['shared']) 
            && $this->bindings[$abstract]['shared'] === true;
    }

    /**
     * Registra un alias
     * 
     * @param string $alias
     * @param string $abstract
     * @return void
     */
    public function alias(string $alias, string $abstract): void
    {
        $this->aliases[$alias] = $abstract;
    }

    /**
     * Obtiene el abstract real desde un alias
     * 
     * @param string $abstract
     * @return string
     */
    private function getAlias(string $abstract): string
    {
        return $this->aliases[$abstract] ?? $abstract;
    }

    /**
     * Verifica si un abstract está registrado
     * 
     * @param string $abstract
     * @return bool
     */
    public function has(string $abstract): bool
    {
        $abstract = $this->getAlias($abstract);
        
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * Llama un método inyectando sus dependencias
     * 
     * @param callable|array|string $callback
     * @param array $parameters
     * @return mixed
     * @throws ContainerException
     */
    public function call($callback, array $parameters = [])
    {
        if (is_string($callback) && strpos($callback, '::') !== false) {
            $callback = explode('::', $callback, 2);
        }

        if (is_array($callback)) {
            [$class, $method] = $callback;
            
            $class = is_string($class) ? $this->make($class) : $class;
            
            try {
                $reflector = new \ReflectionMethod($class, $method);
            } catch (ReflectionException $e) {
                throw new ContainerException("Method [$method] does not exist.", 0, $e);
            }
            
            $dependencies = $reflector->getParameters();
            $instances = $this->resolveDependencies($dependencies, $parameters);
            
            return $reflector->invokeArgs($class, $instances);
        }

        if ($callback instanceof Closure) {
            $reflector = new \ReflectionFunction($callback);
            $dependencies = $reflector->getParameters();
            $instances = $this->resolveDependencies($dependencies, $parameters);
            
            return $reflector->invokeArgs($instances);
        }

        return call_user_func_array($callback, $parameters);
    }
}

/**
 * Excepción del contenedor
 */
class ContainerException extends \Exception
{
}









