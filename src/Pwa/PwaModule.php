<?php

declare(strict_types=1);

namespace App\Pwa;

use App\Audit\AuditLogger;
use App\Auth\AuthService;
use App\Core\Module;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Notification\NotificationService;
use App\Security\Csrf;
use App\Security\RateLimiter;
use Minishlink\WebPush\WebPush;
use RuntimeException;

final class PwaModule implements Module
{
    public function name(): string
    {
        return 'pwa';
    }

    public function register(Router $router): void
    {
        $auth = new AuthService();
        $subscriptions = new PushSubscriptionRepository();
        $audit = new AuditLogger();
        $authenticated = static function (Request $request, callable $next) use ($auth): Response {
            return $auth->user() === null
                ? Response::json(['ok' => false, 'error' => 'برای ادامه وارد حساب شوید.'], 401)
                : $next($request);
        };
        $csrf = static function (Request $request, callable $next): Response {
            return Csrf::validRequest($request)
                ? $next($request)
                : Response::json(['ok' => false, 'error' => 'نشست منقضی شده؛ صفحه را تازه کنید.'], 419);
        };

        $router->get('/api/v1/pwa/config', static fn (): Response => Response::json([
            'ok' => true,
            'push_enabled' => PushConfig::configured() && class_exists(WebPush::class),
            'vapid_public_key' => PushConfig::configured() ? PushConfig::publicKey() : '',
        ]), [$authenticated]);

        $router->post('/api/v1/pwa/subscriptions', static function (Request $request) use ($auth, $subscriptions, $audit): Response {
            try {
                if (!PushConfig::configured() || !class_exists(WebPush::class)) {
                    throw new RuntimeException('ارسال اعلان گوشی هنوز روی سرور فعال نشده است.');
                }
                $user = $auth->user();
                $id = $subscriptions->save((int) $user['id'], $request->all(), $request->header('user-agent'));
                $audit->record((int) $user['id'], 'pwa.push_enabled', 'push_subscription', (string) $id);
                return Response::json(['ok' => true]);
            } catch (RuntimeException $exception) {
                return Response::json(['ok' => false, 'error' => $exception->getMessage()], 422);
            }
        }, [$authenticated, $csrf]);

        $router->post('/api/v1/pwa/subscriptions/remove', static function (Request $request) use ($auth, $subscriptions, $audit): Response {
            $user = $auth->user();
            $endpoint = (string) $request->input('endpoint', '');
            $subscriptions->revoke((int) $user['id'], $endpoint);
            $audit->record((int) $user['id'], 'pwa.push_disabled', 'user', (string) $user['id']);
            return Response::json(['ok' => true]);
        }, [$authenticated, $csrf]);

        $router->post('/api/v1/pwa/test', static function () use ($auth): Response {
            RateLimiter::hit('pwa.test', 5, 3600);
            $user = $auth->user();
            (new NotificationService())->user(
                (int) $user['id'],
                'system.test',
                'اعلان TaskFlow فعال است',
                'از این پس تغییرات مهم وظایف را روی این دستگاه دریافت می‌کنید.',
                '/workspace#notifications',
            );
            return Response::json(['ok' => true]);
        }, [$authenticated, $csrf]);
    }
}
