<?php

declare(strict_types=1);

namespace App\Database;

use App\Core\ModuleRegistry;
use App\Support\Table;
use PDO;
use RuntimeException;

final class Migrator
{
    public function __construct(private readonly ModuleRegistry $modules)
    {
    }

    /** @return list<string> applied migration IDs */
    public function migrate(?array $requestedModules = null): array
    {
        $this->ensureRepository();
        $applied = array_fill_keys($this->applied(), true);
        $completed = [];

        foreach ($this->files($requestedModules) as $id => $path) {
            if (isset($applied[$id])) {
                continue;
            }
            $this->apply($id, $path);
            $completed[] = $id;
        }

        return $completed;
    }

    /** @return list<string> */
    public function applied(): array
    {
        $this->ensureRepository();
        $table = Table::name('migrations');
        $rows = Connection::get()->query("SELECT migration FROM {$table} ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
        return array_values(array_map('strval', $rows));
    }

    /** @return array<string, string> */
    public function files(?array $requestedModules = null): array
    {
        $files = $this->scan('core', APP_ROOT . '/database/migrations');
        $registry = $this->modules->all();
        foreach ($this->modules->enabled($requestedModules) as $name) {
            $files += $this->scan($name, $registry[$name]['migrations']);
        }
        return $files;
    }

    private function ensureRepository(): void
    {
        $table = Table::name('migrations');
        Connection::get()->exec("CREATE TABLE IF NOT EXISTS {$table} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, migration VARCHAR(255) NOT NULL, batch INT UNSIGNED NOT NULL, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY uq_migration (migration)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    private function apply(string $id, string $path): void
    {
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException('Cannot read migration: ' . $path);
        }
        $sql = str_replace('{{prefix}}', Table::prefix(), $sql);
        $pdo = Connection::get();
        foreach ($this->statements($sql) as $statement) {
            $pdo->exec($statement);
        }

        $table = Table::name('migrations');
        $batch = (int) $pdo->query("SELECT COALESCE(MAX(batch), 0) + 1 FROM {$table}")->fetchColumn();
        $insert = $pdo->prepare("INSERT INTO {$table} (migration, batch) VALUES (?, ?)");
        $insert->execute([$id, $batch]);
    }

    /** @return array<string, string> */
    private function scan(string $scope, string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }
        $paths = glob(rtrim($directory, '/') . '/*.sql') ?: [];
        sort($paths, SORT_NATURAL);
        $result = [];
        foreach ($paths as $path) {
            $result[$scope . ':' . basename($path)] = $path;
        }
        return $result;
    }

    /** @return list<string> */
    private function statements(string $sql): array
    {
        $result = [];
        $buffer = '';
        $quote = null;
        $escaped = false;
        $length = strlen($sql);

        for ($index = 0; $index < $length; $index++) {
            $char = $sql[$index];
            $next = $index + 1 < $length ? $sql[$index + 1] : '';

            if ($quote === null && $char === '-' && $next === '-' && ($index === 0 || ctype_space($sql[$index - 1]))) {
                while ($index < $length && $sql[$index] !== "\n") {
                    $index++;
                }
                $buffer .= "\n";
                continue;
            }

            if ($quote !== null) {
                $buffer .= $char;
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }

            if ($char === ';') {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $result[] = $statement;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $tail = trim($buffer);
        if ($tail !== '') {
            $result[] = $tail;
        }
        return $result;
    }
}
