<?php

declare(strict_types=1);

namespace App\Pwa;

use App\Support\Env;

final class PushConfig
{
    public static function publicKey(): string
    {
        return trim(Env::get('PUSH_VAPID_PUBLIC_KEY', '') ?? '');
    }

    public static function privateKey(): string
    {
        return trim(Env::get('PUSH_VAPID_PRIVATE_KEY', '') ?? '');
    }

    public static function subject(): string
    {
        $subject = trim(Env::get('PUSH_VAPID_SUBJECT', '') ?? '');
        if ($subject !== '') {
            return $subject;
        }
        return Env::get('APP_URL', 'https://localhost') ?? 'https://localhost';
    }

    public static function configured(): bool
    {
        return self::validKey(self::publicKey()) && self::validKey(self::privateKey());
    }

    private static function validKey(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{40,140}$/D', $value) === 1;
    }
}
