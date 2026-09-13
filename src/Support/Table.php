<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class Table
{
    public static function name(string $logicalName): string
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $logicalName) !== 1) {
            throw new RuntimeException('Invalid logical table name.');
        }

        $prefix = strtolower(Env::get('DB_TABLE_PREFIX', 'app_') ?? 'app_');
        if (preg_match('/^[a-z][a-z0-9_]{0,30}$/', $prefix) !== 1) {
            throw new RuntimeException('DB_TABLE_PREFIX contains invalid characters.');
        }

        return $prefix . $logicalName;
    }

    public static function prefix(): string
    {
        return substr(self::name('x'), 0, -1);
    }
}
