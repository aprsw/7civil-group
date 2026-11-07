<?php

declare(strict_types=1);

namespace ArtaPharma\Core;

use ArtaPharma\Core\Exceptions\ContainerException;
use Closure;

/**
 * Minimal dependency-injection container (Dependency Inversion Principle).
 *
 * Supports:
 *  - bind(): a factory closure resolved fresh each time.
 *  - singleton(): a factory resolved once and cached (Singleton lifecycle).
 *  - instance(): a pre-built object.
 *  - alias(): bind an interface to a concrete id.
 *
 * Consumers depend on abstractions (interfaces); the container decides the
 * concrete implementation, so higher-level code never news-up its dependencies.
 */
final class Container
{
    /** @var array<string, Closure> */
    private array $bindings = [];

    /** @var array<string, bool> */
    private array $shared = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var array<string, string> interface => concrete id */
    private array $aliases = [];

    public function bind(string $id, Closure $factory, bool $shared = false): void
    {
        $this->bindings[$id] = $factory;
        $this->shared[$id] = $shared;
        unset($this->instances[$id]);
    }

    public function singleton(string $id, Closure $factory): void
    {
        $this->bind($id, $factory, true);
    }

    public function instance(string $id, mixed $object): void
    {
        $this->instances[$id] = $object;
    }

    public function alias(string $abstract, string $concrete): void
    {
        $this->aliases[$abstract] = $concrete;
    }

    public function has(string $id): bool
    {
        $id = $this->aliases[$id] ?? $id;

        return isset($this->bindings[$id]) || isset($this->instances[$id]);
    }

    public function get(string $id): mixed
    {
        $id = $this->aliases[$id] ?? $id;

        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (!isset($this->bindings[$id])) {
            throw new ContainerException("No binding registered for «{$id}».");
        }

        $object = ($this->bindings[$id])($this);

        if ($this->shared[$id] ?? false) {
            $this->instances[$id] = $object;
        }

        return $object;
    }
}
