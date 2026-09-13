<?php

declare(strict_types=1);

namespace App\Feature;

use App\Database\Connection;
use App\Support\Table;
use Throwable;

final class FeatureFlags
{
    /** @var array<string, bool> */
    private array $cache = [];

    public function enabled(string $key, bool $default = false): bool
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        try {
            $table = Table::name('feature_flags');
            $statement = Connection::get()->prepare("SELECT is_enabled FROM {$table} WHERE key_name = ? LIMIT 1");
            $statement->execute([$key]);
            $value = $statement->fetchColumn();
            return $this->cache[$key] = $value === false ? $default : (bool) $value;
        } catch (Throwable) {
            return $this->cache[$key] = $default;
        }
    }
}
