<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Infrastructure;

/**
 * Cross-process lock backed by the active MySQL/MariaDB connection.
 *
 * Named locks work across PHP workers and application servers that share the
 * same database, which makes them suitable for cart, checkout and maintenance
 * critical sections.
 */
final class DatabaseLock
{
    /** @var array<string,bool> */
    private array $held = [];

    public function acquire(string $resource, int $timeoutSeconds = 5): string
    {
        $name = $this->name($resource);
        $timeoutSeconds = min(30, max(0, $timeoutSeconds));
        $result = \Db::getInstance()->getValue(
            "SELECT GET_LOCK('" . pSQL($name) . "'," . $timeoutSeconds . ')'
        );
        if ((int) $result !== 1) {
            throw new \RuntimeException('La operación está siendo procesada por otra solicitud. Inténtelo de nuevo.');
        }
        $this->held[$name] = true;

        return $name;
    }

    public function release(string $name): void
    {
        if ($name === '' || !isset($this->held[$name])) {
            return;
        }
        \Db::getInstance()->getValue("SELECT RELEASE_LOCK('" . pSQL($name) . "')");
        unset($this->held[$name]);
    }

    public function synchronized(string $resource, callable $callback, int $timeoutSeconds = 5): mixed
    {
        $name = $this->acquire($resource, $timeoutSeconds);
        try {
            return $callback();
        } finally {
            $this->release($name);
        }
    }

    public function __destruct()
    {
        foreach (array_keys($this->held) as $name) {
            $this->release($name);
        }
    }

    private function name(string $resource): string
    {
        return 'fabricssamples:' . substr(hash('sha256', trim($resource)), 0, 48);
    }
}
