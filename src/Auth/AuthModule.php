<?php

declare(strict_types=1);

namespace App\Auth;

use App\Audit\AuditLogger;
use App\Core\Module;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Security\Csrf;
use App\Pwa\PushSubscriptionRepository;
use RuntimeException;

final class AuthModule implements Module
{
    public function name(): string
    {
        return 'auth';
    }

    public function register(Router $router): void
    {
        $auth = new AuthService();
        $audit = new AuditLogger();
        $pushSubscriptions = new PushSubscriptionRepository();
        $csrf = static function (Request $request, callable $next): Response {
            if (!Csrf::validRequest($request)) {
                return Response::json(['ok' => false, 'error' => 'The security token has expired. Refresh and try again.'], 419);
            }
            return $next($request);
        };

        $router->get('/api/v1/auth/csrf', static fn (): Response => Response::json([
            'ok' => true,
            'csrf_token' => Csrf::token(),
        ]));

        $router->post('/api/v1/auth/register', static function (Request $request) use ($auth, $audit): Response {
            try {
                $user = $auth->register(
                    (string) $request->input('name', ''),
                    (string) $request->input('email', ''),
                    (string) $request->input('password', ''),
                );
                $audit->record((int) $user['id'], 'auth.register', 'user', (string) $user['id']);
                return Response::json(['ok' => true, 'user' => $user, 'csrf_token' => Csrf::rotate()], 201);
            } catch (RuntimeException $exception) {
                return Response::json(['ok' => false, 'error' => $exception->getMessage()], 422);
            }
        }, [$csrf]);

        $router->post('/api/v1/auth/login', static function (Request $request) use ($auth, $audit, $pushSubscriptions): Response {
            $rememberInput = $request->input('remember_device', false);
            $remember = in_array($rememberInput, [true, 1, '1', 'true', 'on'], true);
            $user = $auth->attempt(
                (string) $request->input('email', ''),
                (string) $request->input('password', ''),
                $remember,
            );
            if ($user === null) {
                return Response::json(['ok' => false, 'error' => 'Email or password is incorrect.'], 401);
            }
            $pushSubscriptions->revokeEndpoint((string) $request->input('push_endpoint', ''));
            $audit->record((int) $user['id'], 'auth.login', 'user', (string) $user['id'], ['remember_device' => $remember]);
            return Response::json(['ok' => true, 'user' => $user, 'csrf_token' => Csrf::rotate()]);
        }, [$csrf]);

        $router->post('/api/v1/auth/logout', static function (Request $request) use ($auth, $audit, $pushSubscriptions): Response {
            $user = $auth->user();
            if ($user !== null) {
                $pushSubscriptions->revoke((int) $user['id'], (string) $request->input('push_endpoint', ''));
                $audit->record((int) $user['id'], 'auth.logout', 'user', (string) $user['id']);
            }
            $auth->logout();
            return Response::json(['ok' => true, 'csrf_token' => Csrf::rotate()]);
        }, [$csrf]);

        $router->get('/api/v1/auth/me', static function () use ($auth): Response {
            $user = $auth->user();
            return $user === null
                ? Response::json(['ok' => false, 'error' => 'Unauthenticated.'], 401)
                : Response::json(['ok' => true, 'user' => $user]);
        });
    }
}
