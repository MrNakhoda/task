<?php

declare(strict_types=1);

namespace App\Security;

final class RequestGuard
{
    private const MAX_WEB_BODY_BYTES = 32_000_000;
    private const MAX_INPUT_SCALARS = 800;
    private const MAX_INPUT_DEPTH = 8;

    public static function preprocess(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        self::headers();
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'], true)) {
            http_response_code(405);
            header('Allow: GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS');
            exit('Method Not Allowed');
        }

        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($contentLength < 0 || $contentLength > self::MAX_WEB_BODY_BYTES) {
            http_response_code(413);
            exit('Payload Too Large');
        }

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            self::assertSupportedContentType();
        }

        $scalars = 0;
        foreach ([$_GET, $_POST, $_COOKIE] as $source) {
            self::inspect($source, 0, $scalars);
        }
        if ($scalars > self::MAX_INPUT_SCALARS) {
            http_response_code(400);
            exit('Too many request fields');
        }
    }

    private static function headers(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
        header('Cross-Origin-Resource-Policy: same-site');
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");

        $forwardedProto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0] ?? ''));
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? '')) === 'on'
            || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443'
            || $forwardedProto === 'https';
        if ($https) {
            header('Strict-Transport-Security: max-age=31536000');
        }
    }

    private static function assertSupportedContentType(): void
    {
        $contentType = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
        if ($contentType === '') {
            return;
        }
        foreach (['application/json', 'application/x-www-form-urlencoded', 'multipart/form-data'] as $allowed) {
            if (str_starts_with($contentType, $allowed)) {
                return;
            }
        }
        http_response_code(415);
        exit('Unsupported Media Type');
    }

    private static function inspect(mixed $value, int $depth, int &$scalars): void
    {
        if ($depth > self::MAX_INPUT_DEPTH) {
            http_response_code(400);
            exit('Request nesting is too deep');
        }
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if (str_contains((string) $key, "\0")) {
                    http_response_code(400);
                    exit('Invalid request field');
                }
                self::inspect($item, $depth + 1, $scalars);
            }
            return;
        }
        $scalars++;
        if (is_string($value) && str_contains($value, "\0")) {
            http_response_code(400);
            exit('Invalid request value');
        }
    }
}
