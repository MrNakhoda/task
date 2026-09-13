<?php

declare(strict_types=1);

namespace App\Support;

final class Url
{
    public static function to(string $path = '/'): string
    {
        $base = rtrim(Env::get('APP_URL', '') ?? '', '/');
        return $base . '/' . ltrim($path, '/');
    }

    public static function requestPath(): string
    {
        $requestPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
        $basePath = (string) (parse_url(Env::get('APP_URL', '') ?? '', PHP_URL_PATH) ?: '');
        $basePath = rtrim($basePath, '/');

        if ($basePath !== '' && ($requestPath === $basePath || str_starts_with($requestPath, $basePath . '/'))) {
            $requestPath = substr($requestPath, strlen($basePath)) ?: '/';
        }

        return '/' . ltrim($requestPath, '/');
    }
}
