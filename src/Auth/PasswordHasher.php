<?php

declare(strict_types=1);

namespace App\Auth;

final class PasswordHasher
{
    public function hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_DEFAULT);
    }

    public function verify(string $plain, string $hash): bool
    {
        return $hash !== '' && password_verify($plain, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return $hash === '' || password_needs_rehash($hash, PASSWORD_DEFAULT);
    }
}
