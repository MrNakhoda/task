<?php

declare(strict_types=1);

use App\Core\Application;
use App\Database\Connection;
use App\Security\RequestGuard;
use App\Support\Env;
use App\Support\Logger;

const APP_ROOT = __DIR__;

$composerAutoload = APP_ROOT . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'App\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $path = APP_ROOT . '/src/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($path)) {
            require_once $path;
        }
    });
}

Env::load(APP_ROOT . '/.env');

$environment = strtolower(Env::get('APP_ENV', 'production') ?? 'production');
$debug = Env::bool('APP_DEBUG', false) && $environment !== 'production';
$timezone = Env::get('APP_TIMEZONE', 'UTC') ?? 'UTC';
date_default_timezone_set($timezone);

error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('display_startup_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');

if ($environment === 'production' && strlen(Env::get('APP_KEY', '') ?? '') < 32) {
    throw new RuntimeException('APP_KEY must contain at least 32 characters in production.');
}

set_exception_handler(static function (Throwable $exception) use ($debug): void {
    Logger::exception($exception);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, ($debug ? (string) $exception : 'Application error') . PHP_EOL);
        exit(1);
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, max-age=0');
    }
    $message = $debug ? $exception->getMessage() : 'An unexpected error occurred.';
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
});

if (PHP_SAPI !== 'cli') {
    RequestGuard::preprocess();

    if (session_status() !== PHP_SESSION_ACTIVE) {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');

        $appUrl = strtolower(Env::get('APP_URL', '') ?? '');
        $secure = str_starts_with($appUrl, 'https://')
            || (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off');

        session_set_cookie_params([
            'httponly' => true,
            'secure' => $secure,
            'samesite' => 'Lax',
            'path' => '/',
        ]);
        session_start();
    }
}

Connection::configure([
    'host' => Env::get('DB_HOST', '127.0.0.1'),
    'port' => Env::get('DB_PORT', '3306'),
    'name' => Env::get('DB_NAME', ''),
    'user' => Env::get('DB_USER', ''),
    'pass' => Env::get('DB_PASS', ''),
]);

/** @var array<string, array{class: class-string, migrations: string, depends: list<string>}> $moduleRegistry */
$moduleRegistry = require APP_ROOT . '/config/modules.php';

return Application::fromRegistry($moduleRegistry);
