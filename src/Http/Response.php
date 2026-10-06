<?php

declare(strict_types=1);

namespace App\Http;

use App\Support\Url;

final class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        private readonly string $body = '',
        private readonly int $status = 200,
        private readonly array $headers = [],
        private readonly ?string $filePath = null,
    ) {
    }

    public static function json(array $data, int $status = 200, array $headers = []): self
    {
        return new self(
            json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $status,
            ['Content-Type' => 'application/json; charset=UTF-8'] + $headers,
        );
    }

    public static function html(string $html, int $status = 200, array $headers = []): self
    {
        return new self($html, $status, ['Content-Type' => 'text/html; charset=UTF-8'] + $headers);
    }

    public static function redirect(string $path, int $status = 303): self
    {
        return new self('', $status, ['Location' => Url::to($path), 'Cache-Control' => 'no-store']);
    }

    public static function noContent(): self
    {
        return new self('', 204);
    }

    public static function file(string $path, string $mimeType, string $filename, bool $inline = true): self
    {
        if (!is_file($path) || !is_readable($path)) {
            return self::json(['ok' => false, 'error' => 'فایل پیدا نشد.'], 404);
        }
        $safeName = str_replace(["\r", "\n", '"'], '', $filename);
        return new self('', 200, [
            'Content-Type' => $mimeType,
            'Content-Length' => (string) filesize($path),
            'Content-Disposition' => ($inline ? 'inline' : 'attachment') . '; filename="' . $safeName . '"; filename*=UTF-8\'\'' . rawurlencode($safeName),
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ], $path);
    }

    public function send(): never
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        if ($this->filePath !== null) {
            $handle = fopen($this->filePath, 'rb');
            if ($handle !== false) {
                fpassthru($handle);
                fclose($handle);
            }
        } else {
            echo $this->body;
        }
        exit;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }
}
