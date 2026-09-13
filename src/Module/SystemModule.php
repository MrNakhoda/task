<?php

declare(strict_types=1);

namespace App\Module;

use App\Auth\AuthService;
use App\Core\Module;
use App\Database\Connection;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Http\View;
use App\Support\Env;
use Throwable;

final class SystemModule implements Module
{
    /** @param list<string> $enabledModules */
    public function __construct(private readonly array $enabledModules)
    {
    }

    public function name(): string
    {
        return 'system';
    }

    public function register(Router $router): void
    {
        $auth = new AuthService();

        $router->get('/health', function (): Response {
            $database = true;
            try {
                Connection::get()->query('SELECT 1')->fetchColumn();
            } catch (Throwable) {
                $database = false;
            }
            return Response::json([
                'ok' => $database,
                'service' => Env::get('APP_NAME', 'Backend Starter'),
                'environment' => Env::get('APP_ENV', 'production'),
                'database' => $database ? 'ready' : 'unavailable',
                'time' => gmdate(DATE_ATOM),
            ], $database ? 200 : 503);
        });

        $router->get('/api/v1/meta', fn (): Response => Response::json([
            'ok' => true,
            'name' => Env::get('APP_NAME', 'Backend Starter'),
            'locale' => Env::get('APP_LOCALE', 'fa'),
            'modules' => $this->enabledModules,
        ]));

        $router->get('/', fn (): Response => View::page('home', [
            'user' => $auth->user(),
            'modules' => $this->enabledModules,
        ]));

        $router->get('/login', static function () use ($auth): Response {
            return $auth->user() !== null ? Response::redirect('/dashboard') : View::page('login');
        });

        $router->get('/register', static function () use ($auth): Response {
            return $auth->user() !== null ? Response::redirect('/dashboard') : View::page('register');
        });

        $router->get('/dashboard', fn (): Response => $auth->user() === null
            ? Response::redirect('/login')
            : View::page('dashboard', ['user' => $auth->user(), 'modules' => $this->enabledModules]));

        $router->get('/workspace', fn (): Response => $auth->user() === null
            ? Response::redirect('/login')
            : View::page('workspace', ['user' => $auth->user()]));
    }
}
