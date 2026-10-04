<?php

namespace RestApiToolkit\Core;

/**
 * Contenedor de inyección de dependencias con autowiring por reflexión.
 */
class Container
{
    /** @var array<string, callable> */
    private $factories = [];

    /** @var array<string, object> */
    private $instances = [];

    public function singleton(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->factories[$id]) || class_exists($id);
    }

    /**
     * @template T
     * @param class-string<T> $id
     * @return T
     */
    public function get(string $id)
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }
        if (isset($this->factories[$id])) {
            return $this->instances[$id] = ($this->factories[$id])($this);
        }

        return $this->instances[$id] = $this->build($id);
    }

    /**
     * Construye una clase resolviendo dependencias tipadas del constructor.
     */
    private function build(string $class)
    {
        if (!class_exists($class)) {
            throw new \RuntimeException(sprintf('Clase no encontrada en el contenedor: %s', $class));
        }

        $reflection = new \ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if (!$constructor || $constructor->getNumberOfParameters() === 0) {
            return new $class();
        }

        $args = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $args[] = $this->get($type->getName());
                continue;
            }
            if ($parameter->isDefaultValueAvailable()) {
                $args[] = $parameter->getDefaultValue();
                continue;
            }
            throw new \RuntimeException(sprintf(
                'No se puede resolver el parámetro "%s" de %s.',
                $parameter->getName(),
                $class
            ));
        }

        return $reflection->newInstanceArgs($args);
    }
}
