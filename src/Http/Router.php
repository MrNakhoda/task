<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

final class Router
{
    /** @var list<array{method: string, regex: string, names: list<string>, handler: callable, middleware: list<callable>}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    public function add(string $method, string $pattern, callable $handler, array $middleware = []): void
    {
        [$regex, $names] = $this->compile($pattern);
        $this->routes[] = [
            'method' => strtoupper($method),
            'regex' => $regex,
            'names' => $names,
            'handler' => $handler,
            'middleware' => array_values($middleware),
        ];
    }

    public function dispatch(Request $request): Response
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method()) {
                continue;
            }
            if (preg_match($route['regex'], $request->path(), $matches) !== 1) {
                continue;
            }

            $params = [];
            foreach ($route['names'] as $name) {
                $params[$name] = urldecode((string) ($matches[$name] ?? ''));
            }

            $next = static fn (Request $nextRequest): Response => ($route['handler'])($nextRequest, $params);
            foreach (array_reverse($route['middleware']) as $middleware) {
                $downstream = $next;
                $next = static fn (Request $nextRequest): Response => $middleware($nextRequest, $downstream);
            }

            $response = $next($request);
            if (!$response instanceof Response) {
                throw new RuntimeException('Route handlers must return an HTTP Response.');
            }
            return $response;
        }

        return Response::json(['ok' => false, 'error' => 'Not found.'], 404);
    }

    /** @return array{string, list<string>} */
    private function compile(string $pattern): array
    {
        $pattern = '/' . trim($pattern, '/');
        if ($pattern === '/') {
            return ['#^/$#', []];
        }

        $names = [];
        $parts = array_map(static function (string $segment) use (&$names): string {
            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $segment, $match) === 1) {
                $names[] = $match[1];
                return '(?P<' . $match[1] . '>[^/]+)';
            }
            return preg_quote($segment, '#');
        }, explode('/', trim($pattern, '/')));

        return ['#^/' . implode('/', $parts) . '/?$#', $names];
    }
}
