<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\NotFoundException;
use Closure;
use Psr\Container\ContainerInterface;

/**
 * Minimal dependency-injection container. Every long-lived collaborator
 * (Config, Logger, Database, ExchangeManager, ...) is registered here once
 * at bootstrap and resolved by class name everywhere else, so modules never
 * new() their own dependencies and stay swappable/testable (DIP).
 */
final class Application implements ContainerInterface
{
    /** @var array<string, Closure(self): mixed> */
    private array $factories = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var array<string, true> */
    private array $singletons = [];

    public function singleton(string $id, Closure $factory): void
    {
        $this->factories[$id] = $factory;
        $this->singletons[$id] = true;
        unset($this->instances[$id]);
    }

    public function bind(string $id, Closure $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->singletons[$id], $this->instances[$id]);
    }

    public function instance(string $id, mixed $value): void
    {
        $this->instances[$id] = $value;
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (!isset($this->factories[$id])) {
            throw new NotFoundException("No binding registered for [$id].");
        }

        $value = ($this->factories[$id])($this);

        if (isset($this->singletons[$id])) {
            $this->instances[$id] = $value;
        }

        return $value;
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]) || array_key_exists($id, $this->instances);
    }
}
