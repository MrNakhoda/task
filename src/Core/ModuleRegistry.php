<?php

declare(strict_types=1);

namespace App\Core;

use App\Support\Env;
use RuntimeException;

final class ModuleRegistry
{
    /** @param array<string, array{class: class-string, migrations: string, depends: list<string>}> $registry */
    public function __construct(private readonly array $registry)
    {
    }

    /** @return list<string> */
    public function enabled(?array $requested = null): array
    {
        $requested ??= Env::csv('APP_MODULES');
        $resolved = [];
        $visiting = [];

        $visit = function (string $name) use (&$visit, &$resolved, &$visiting): void {
            if (in_array($name, $resolved, true)) {
                return;
            }
            if (isset($visiting[$name])) {
                throw new RuntimeException('Circular module dependency: ' . $name);
            }
            if (!isset($this->registry[$name])) {
                throw new RuntimeException('Unknown module: ' . $name);
            }
            $visiting[$name] = true;
            foreach ($this->registry[$name]['depends'] as $dependency) {
                $visit($dependency);
            }
            unset($visiting[$name]);
            $resolved[] = $name;
        };

        foreach ($requested as $name) {
            $visit(strtolower(trim((string) $name)));
        }

        return $resolved;
    }

    /** @return array<string, array{class: class-string, migrations: string, depends: list<string>}> */
    public function all(): array
    {
        return $this->registry;
    }
}
