<?php

declare(strict_types=1);

$uriPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
$publicRoot = realpath(__DIR__);
$requestedFile = realpath(__DIR__ . $uriPath);

if (
    $uriPath !== '/'
    && $publicRoot !== false
    && $requestedFile !== false
    && str_starts_with($requestedFile, $publicRoot . DIRECTORY_SEPARATOR)
    && is_file($requestedFile)
) {
    return false;
}

require __DIR__ . '/index.php';
