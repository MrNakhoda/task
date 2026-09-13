<?php

declare(strict_types=1);

namespace App\Http;

use App\Support\Url;

final class Request
{
    /** @param array<string, mixed> $input */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $input = [],
        private readonly array $headers = [],
        private readonly array $files = [],
        private readonly array $query = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        if (!is_array($headers)) {
            $headers = [];
        }

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$name] = (string) $value;
            }
        }

        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        $input = $_POST;
        if (str_starts_with($contentType, 'application/json')) {
            $decoded = json_decode((string) file_get_contents('php://input'), true);
            $input = is_array($decoded) ? $decoded : [];
        }

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            Url::requestPath(),
            $input,
            array_change_key_case($headers, CASE_LOWER),
            $_FILES,
            $_GET,
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->input[$key] ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;
        return is_array($file) ? $file : null;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $value = $this->headers[strtolower($name)] ?? $default;
        return is_scalar($value) || $value === null ? ($value === null ? null : (string) $value) : $default;
    }

    public function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->input;
    }
}
