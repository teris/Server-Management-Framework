<?php
namespace SMF\Core;
use Closure;
use LogicException;
final class Container {
    private array $factories = [];
    private array $instances = [];
    private array $resolving = [];
    public function bind(string $id, Closure $factory): void {
        if (isset($this->instances[$id])) throw new LogicException('Service already resolved: ' . $id);
        $this->factories[$id] = $factory;
    }
    public function get(string $id): mixed {
        if (array_key_exists($id, $this->instances)) return $this->instances[$id];
        if (!isset($this->factories[$id])) throw new LogicException('Unknown service: ' . $id);
        if (isset($this->resolving[$id])) throw new LogicException('Circular service dependency: ' . $id);
        $this->resolving[$id] = true;
        try { return $this->instances[$id] = ($this->factories[$id])($this); }
        finally { unset($this->resolving[$id]); }
    }
}
