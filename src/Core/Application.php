<?php

declare(strict_types=1);

namespace App\Core;

use App\Auth\AuthModule;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Module\SystemModule;
use App\Pwa\PwaModule;
use App\Support\Env;
use App\Support\Logger;
use Throwable;

final class Application
{
    /** @param list<string> $enabledModules */
    private function __construct(
        private readonly Router $router,
        private readonly array $enabledModules,
    ) {
    }

    /** @param array<string, array{class: class-string, migrations: string, depends: list<string>}> $registry */
    public static function fromRegistry(array $registry): self
    {
        $router = new Router();
        $modules = new ModuleRegistry($registry);
        $enabled = $modules->enabled();

        (new SystemModule($enabled))->register($router);
        (new AuthModule())->register($router);
        (new PwaModule())->register($router);

        foreach ($enabled as $name) {
            $class = $registry[$name]['class'];
            $module = new $class();
            if (!$module instanceof Module) {
                throw new \RuntimeException($class . ' must implement ' . Module::class);
            }
            $module->register($router);
        }

        return new self($router, $enabled);
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->router->dispatch($request);
        } catch (Throwable $exception) {
            Logger::exception($exception);
            $message = Env::bool('APP_DEBUG', false) && strtolower(Env::get('APP_ENV', 'production') ?? '') !== 'production'
                ? $exception->getMessage()
                : 'An unexpected error occurred.';
            return Response::json(['ok' => false, 'error' => $message], 500);
        }
    }

    /** @return list<string> */
    public function enabledModules(): array
    {
        return $this->enabledModules;
    }
}
