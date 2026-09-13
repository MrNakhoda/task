<?php

declare(strict_types=1);

namespace App\Http;

use App\Security\Csrf;
use App\Support\Env;
use App\Support\Url;
use RuntimeException;

final class View
{
    public static function page(string $view, array $data = [], int $status = 200): Response
    {
        if (preg_match('/^[a-z0-9_-]+$/', $view) !== 1) {
            throw new RuntimeException('Invalid view name.');
        }

        $viewPath = APP_ROOT . '/views/' . $view . '.php';
        $layoutPath = APP_ROOT . '/views/layout.php';
        if (!is_file($viewPath) || !is_file($layoutPath)) {
            throw new RuntimeException('View file is missing.');
        }

        $appName = Env::get('APP_NAME', 'Backend Starter') ?? 'Backend Starter';
        $csrfToken = PHP_SAPI === 'cli' ? '' : Csrf::token();
        $to = static fn (string $path = '/'): string => Url::to($path);
        extract($data, EXTR_SKIP);

        ob_start();
        require $viewPath;
        $content = (string) ob_get_clean();

        ob_start();
        require $layoutPath;
        return Response::html((string) ob_get_clean(), $status);
    }
}
