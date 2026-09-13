<?php

declare(strict_types=1);

namespace App\Security;

use App\Http\Request;

final class Csrf
{
    private const SESSION_KEY = 'app_csrf_token';

    public static function token(): string
    {
        if (!isset($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::SESSION_KEY];
    }

    public static function valid(?string $candidate): bool
    {
        $expected = $_SESSION[self::SESSION_KEY] ?? null;
        return is_string($expected) && is_string($candidate) && hash_equals($expected, $candidate);
    }

    public static function validRequest(Request $request): bool
    {
        $candidate = $request->header('X-CSRF-Token');
        if ($candidate === null) {
            $value = $request->input('_token');
            $candidate = is_string($value) ? $value : null;
        }
        return self::valid($candidate);
    }

    public static function rotate(): string
    {
        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        return $_SESSION[self::SESSION_KEY];
    }
}
