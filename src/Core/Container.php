<?php

declare(strict_types=1);

namespace App\Core;

use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

/**
 * Tiny DI container: explicit factories for things that need configuration (PDO…),
 * reflection-based autowiring for everything else (controllers, services, repositories).
 * Every resolved instance is shared (singleton per request).
 */
final class Container
{
    /** @var array<string, callable(self): object> */
    private array $factories = [];

    /** @var array<string, object> */
    private array $instances = [];

    /** @param callable(self): object $factory */
    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        $instance = isset($this->factories[$id])
            ? ($this->factories[$id])($this)
            : $this->autowire($id);

        return $this->instances[$id] = $instance;
    }

    private function autowire(string $class): object
    {
        if (!class_exists($class)) {
            throw new RuntimeException("Cannot resolve '$class': class does not exist.");
        }

        $reflection = new ReflectionClass($class);
        if (!$reflection->isInstantiable()) {
            throw new RuntimeException("Cannot resolve '$class': not instantiable, register a factory.");
        }

        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return new $class();
        }

        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $args[] = $this->get($type->getName());
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                throw new RuntimeException("Cannot autowire parameter \${$param->getName()} of '$class'.");
            }
        }

        return $reflection->newInstanceArgs($args);
    }
}
