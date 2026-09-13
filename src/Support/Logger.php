<?php

declare(strict_types=1);

namespace App\Support;

use Throwable;

final class Logger
{
    public static function error(string $message, array $context = []): void
    {
        $safeMessage = str_replace(["\r", "\n"], ' ', $message);
        $encoded = $context === [] ? '' : ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        error_log(sprintf('[%s] %s%s', Env::get('APP_NAME', 'App'), $safeMessage, $encoded));
    }

    public static function exception(Throwable $exception): void
    {
        self::error($exception::class . ': ' . $exception->getMessage(), [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);
    }
}
