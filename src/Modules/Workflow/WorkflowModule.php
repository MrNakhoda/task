<?php

declare(strict_types=1);

namespace App\Modules\Workflow;

use App\Auth\Authorization;
use App\Auth\AuthService;
use App\Core\Module;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Media\UploadService;
use App\Security\Csrf;
use RuntimeException;

final class WorkflowModule implements Module
{
    public function name(): string
    {
        return 'workflow';
    }

    public function register(Router $router): void
    {
        $auth = new AuthService();
        $authorization = new Authorization();
        $repository = new WorkflowRepository();
        $engine = new WorkflowEngine($repository);

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
        $permission = static function (string $key) use ($auth, $authorization): callable {
            return static function (Request $request, callable $next) use ($auth, $authorization, $key): Response {
                $user = $auth->user();
                return $user !== null && ($authorization->allows((int) $user['id'], $key) || $authorization->allows((int) $user['id'], 'workflow.admin') || $authorization->allows((int) $user['id'], 'system.admin'))
                    ? $next($request)
                    : Response::json(['ok' => false, 'error' => 'دسترسی کافی ندارید.'], 403);
            };
        };
        $endpoint = static function (callable $handler, int $status = 200): callable {
            return static function (Request $request, array $params = []) use ($handler, $status): Response {
                try {
                    $data = $handler($request, $params);
                    return Response::json(['ok' => true] + (is_array($data) ? $data : []), $status);
                } catch (RuntimeException $exception) {
                    return Response::json(['ok' => false, 'error' => $exception->getMessage()], 422);
                }
            };
        };
        $actor = static function () use ($auth): int {
            return (int) ($auth->user()['id'] ?? 0);
        };
        $mayManageTasks = static function (int $userId) use ($authorization): bool {
            return $authorization->allows($userId, 'tasks.manage') || $authorization->allows($userId, 'workflow.admin') || $authorization->allows($userId, 'system.admin');
        };

        $router->get('/api/v1/workflow/overview', $endpoint(static fn () => ['overview' => $repository->overview($actor(), $mayManageTasks($actor()))]), [$authenticated]);
        $router->get('/api/v1/workflow/reference', $endpoint(static fn () => ['reference' => $repository->referenceData()]), [$authenticated]);
        $router->get('/api/v1/workflow/templates', $endpoint(static fn () => ['templates' => $repository->templates()]), [$authenticated]);
        $router->get('/api/v1/workflow/orders', $endpoint(static fn (Request $request) => ['orders' => $repository->orders([
            'status' => $request->query('status', ''),
            'priority_id' => $request->query('priority_id', ''),
            'customer' => $request->query('customer', ''),
            'weight' => $request->query('weight', ''),
        ])]), [$authenticated]);
        $router->get('/api/v1/workflow/orders/{id}', $endpoint(static function (Request $request, array $params) use ($repository): array {
            $order = $repository->order((int) ($params['id'] ?? 0));
            if ($order === null) throw new RuntimeException('سفارش پیدا نشد.');
            return ['order' => $order];
        }), [$authenticated]);
        $router->get('/api/v1/workflow/tasks', $endpoint(static fn (Request $request) => ['tasks' => $repository->tasks($actor(), $mayManageTasks($actor()), [
            'status' => $request->query('status', ''),
            'task_type_id' => $request->query('task_type_id', ''),
            'order_id' => $request->query('order_id', ''),
            'customer' => $request->query('customer', ''),
        ])]), [$authenticated]);
        $router->get('/api/v1/workflow/notifications', $endpoint(static fn () => ['notifications' => $repository->notifications($actor())]), [$authenticated]);

        $router->post('/api/v1/workflow/templates', $endpoint(static fn (Request $request) => ['id' => $repository->createTemplate((string) $request->input('name', ''), $request->input('description'), $actor())], 201), [$authenticated, $csrf, $permission('templates.manage')]);
        $router->post('/api/v1/workflow/templates/{id}', $endpoint(static function (Request $request, array $params) use ($repository): array {
            $repository->updateTemplate((int) ($params['id'] ?? 0), $request->all());
            return [];
        }), [$authenticated, $csrf, $permission('templates.manage')]);
        $router->post('/api/v1/workflow/templates/{id}/steps', $endpoint(static fn (Request $request, array $params) => ['id' => $repository->addTemplateStep((int) ($params['id'] ?? 0), $request->all())], 201), [$authenticated, $csrf, $permission('templates.manage')]);
        $router->post('/api/v1/workflow/template-steps/{id}', $endpoint(static function (Request $request, array $params) use ($repository): array {
            $repository->updateTemplateStep((int) ($params['id'] ?? 0), $request->all());
            return [];
        }), [$authenticated, $csrf, $permission('templates.manage')]);
        $router->post('/api/v1/workflow/template-steps/{id}/disable', $endpoint(static function (Request $request, array $params) use ($repository): array {
            $repository->disableTemplateStep((int) ($params['id'] ?? 0));
            return [];
        }), [$authenticated, $csrf, $permission('templates.manage')]);
        $router->post('/api/v1/workflow/orders', $endpoint(static fn (Request $request) => ['id' => $repository->createOrder($request->all(), $actor())], 201), [$authenticated, $csrf, $permission('orders.manage')]);
        $router->post('/api/v1/workflow/orders/{id}/activate', $endpoint(static function (Request $request, array $params) use ($engine, $actor): array {
            $engine->activateOrder((int) ($params['id'] ?? 0), $actor());
            return [];
        }), [$authenticated, $csrf, $permission('orders.manage')]);
        $router->post('/api/v1/workflow/orders/{id}/steps', $endpoint(static fn (Request $request, array $params) => ['id' => $engine->addOrderStep((int) ($params['id'] ?? 0), $actor(), $request->all())], 201), [$authenticated, $csrf, $permission('orders.manage')]);
        $router->post('/api/v1/workflow/order-steps/{id}/disable', $endpoint(static function (Request $request, array $params) use ($engine, $actor): array {
            $engine->disableOrderStep((int) ($params['id'] ?? 0), $actor());
            return [];
        }), [$authenticated, $csrf, $permission('orders.manage')]);
        $router->post('/api/v1/workflow/order-steps/{id}/dependencies', $endpoint(static function (Request $request, array $params) use ($engine, $actor): array {
            $engine->setStepDependencies((int) ($params['id'] ?? 0), $actor(), (array) $request->input('dependency_ids', []));
            return [];
        }), [$authenticated, $csrf, $permission('orders.manage')]);
        $router->post('/api/v1/workflow/orders/{id}/steps/reorder', $endpoint(static function (Request $request, array $params) use ($engine, $actor): array {
            $engine->reorderSteps((int) ($params['id'] ?? 0), $actor(), (array) $request->input('step_ids', []));
            return [];
        }), [$authenticated, $csrf, $permission('orders.manage')]);

        $router->post('/api/v1/workflow/tasks/{id}/start', $endpoint(static function (Request $request, array $params) use ($engine, $actor, $mayManageTasks): array {
            $userId = $actor();
            $engine->startTask((int) ($params['id'] ?? 0), $userId, $mayManageTasks($userId));
            return [];
        }), [$authenticated, $csrf]);
        $router->post('/api/v1/workflow/tasks/{id}/reports', $endpoint(static fn (Request $request, array $params) => ['id' => $engine->addReport((int) ($params['id'] ?? 0), $actor(), (string) $request->input('report_text', ''), $mayManageTasks($actor()))], 201), [$authenticated, $csrf]);
        $router->post('/api/v1/workflow/tasks/{id}/complete', $endpoint(static function (Request $request, array $params) use ($engine, $actor, $mayManageTasks): array {
            $userId = $actor();
            $engine->completeTask((int) ($params['id'] ?? 0), $userId, $mayManageTasks($userId), $request->input('report_text'));
            return [];
        }), [$authenticated, $csrf]);
        $router->post('/api/v1/workflow/tasks/{id}/assignees', $endpoint(static function (Request $request, array $params) use ($engine, $actor): array {
            $engine->reassignTask((int) ($params['id'] ?? 0), $actor(), (array) $request->input('user_ids', []));
            return [];
        }), [$authenticated, $csrf, $permission('tasks.manage')]);
        $router->post('/api/v1/workflow/notifications/read', $endpoint(static function () use ($repository, $actor): array {
            $repository->markNotificationsRead($actor());
            return [];
        }), [$authenticated, $csrf]);

        $router->post('/api/v1/workflow/task-types', $endpoint(static fn (Request $request) => ['id' => $repository->createTaskType((string) $request->input('name', ''), (string) $request->input('slug', ''), (string) $request->input('color', '#3157d5'), $request->input('description'))], 201), [$authenticated, $csrf, $permission('templates.manage')]);
        $router->post('/api/v1/workflow/priorities', $endpoint(static fn (Request $request) => ['id' => $repository->createPriority((string) $request->input('key_name', ''), (string) $request->input('name', ''), (string) $request->input('color', '#64748b'), (int) $request->input('sort_order', 0))], 201), [$authenticated, $csrf, $permission('orders.manage')]);
        $router->post('/api/v1/workflow/customers', $endpoint(static fn (Request $request) => ['id' => $repository->createCustomer((string) $request->input('name', ''), $request->input('phone'), $request->input('email'), $request->input('notes'))], 201), [$authenticated, $csrf, $permission('orders.manage')]);
        $router->post('/api/v1/workflow/teams', $endpoint(static fn (Request $request) => ['id' => $repository->createTeam((string) $request->input('name', ''), $request->input('description'), $actor())], 201), [$authenticated, $csrf, $permission('teams.manage')]);
        $router->post('/api/v1/workflow/teams/{id}/members', $endpoint(static function (Request $request, array $params) use ($repository): array {
            $repository->addTeamMember((int) ($params['id'] ?? 0), (int) $request->input('user_id', 0), (bool) $request->input('is_lead', false));
            return [];
        }), [$authenticated, $csrf, $permission('teams.manage')]);
        $router->post('/api/v1/workflow/projects', $endpoint(static fn (Request $request) => ['id' => $repository->createProject((string) $request->input('name', ''), $request->input('code'), $request->input('description'), $actor())], 201), [$authenticated, $csrf, $permission('teams.manage')]);
        $router->post('/api/v1/workflow/projects/{id}/members', $endpoint(static function (Request $request, array $params) use ($repository): array {
            $repository->addProjectMember((int) ($params['id'] ?? 0), (int) $request->input('user_id', 0), $request->input('role_label'));
            return [];
        }), [$authenticated, $csrf, $permission('teams.manage')]);
        $router->post('/api/v1/workflow/users', $endpoint(static fn (Request $request) => ['id' => $repository->createUser((string) $request->input('name', ''), (string) $request->input('email', ''), (string) $request->input('password', ''), (string) $request->input('role_key', 'user'))], 201), [$authenticated, $csrf, $permission('users.manage')]);
        $router->post('/api/v1/workflow/roles', $endpoint(static fn (Request $request) => ['id' => $repository->createRole((string) $request->input('key_name', ''), (string) $request->input('display_name', ''), (array) $request->input('permissions', []))], 201), [$authenticated, $csrf, $permission('users.manage')]);
        $router->post('/api/v1/workflow/users/{id}/roles', $endpoint(static function (Request $request, array $params) use ($repository): array {
            $repository->assignRole((int) ($params['id'] ?? 0), (int) $request->input('role_id', 0));
            return [];
        }), [$authenticated, $csrf, $permission('users.manage')]);

        $router->post('/api/v1/workflow/orders/{id}/attachments', $endpoint(static function (Request $request, array $params) use ($repository, $actor): array {
            $file = $request->file('image');
            if ($file === null) throw new RuntimeException('تصویر انتخاب نشده است.');
            $upload = new UploadService();
            $path = $upload->storeImage($file, 'orders');
            try {
                return ['id' => $repository->attachOrderImage((int) ($params['id'] ?? 0), $actor(), $path, (string) ($file['name'] ?? ''), (string) ($file['type'] ?? 'application/octet-stream'), (int) ($file['size'] ?? 0), $request->input('caption'))];
            } catch (\Throwable $exception) {
                $upload->delete($path);
                throw $exception;
            }
        }, 201), [$authenticated, $csrf, $permission('orders.manage')]);
    }
}
