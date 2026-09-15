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
        $allows = static function (int $userId, string $key) use ($authorization): bool {
            return $authorization->allows($userId, $key) || $authorization->allows($userId, 'workflow.admin') || $authorization->allows($userId, 'system.admin');
        };
        $permission = static function (string $key) use ($auth, $allows): callable {
            return static function (Request $request, callable $next) use ($auth, $allows, $key): Response {
                $user = $auth->user();
                return $user !== null && $allows((int) $user['id'], $key)
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
        $mayManageTasks = static function (int $userId) use ($allows): bool {
            return $allows($userId, 'tasks.manage');
        };
        $mayManageProjects = static function (int $userId) use ($allows): bool {
            return $allows($userId, 'orders.manage');
        };
        $isSystemAdmin = static function (int $userId) use ($authorization): bool {
            return $authorization->allows($userId, 'system.admin');
        };
        $systemAdmin = static function (Request $request, callable $next) use ($auth, $isSystemAdmin): Response {
            $user = $auth->user();
            return $user !== null && $isSystemAdmin((int) $user['id'])
                ? $next($request)
                : Response::json(['ok' => false, 'error' => 'این عملیات فقط برای ادمین اصلی مجاز است.'], 403);
        };

        $router->get('/api/v1/workflow/overview', $endpoint(static fn () => ['overview' => $repository->overview($actor(), $mayManageProjects($actor()), $mayManageTasks($actor()))]), [$authenticated]);
        $router->get('/api/v1/workflow/reference', $endpoint(static function () use ($repository, $actor, $allows, $mayManageProjects, $mayManageTasks, $isSystemAdmin): array {
            $userId = $actor();
            $usersManage = $allows($userId, 'users.manage');
            $rolesManage = $allows($userId, 'roles.manage');
            $projectsManage = $mayManageProjects($userId);
            $tasksManage = $mayManageTasks($userId);
            $templatesManage = $allows($userId, 'templates.manage');
            $teamsManage = $allows($userId, 'teams.manage');
            $capabilities = [
                'system_admin' => $isSystemAdmin($userId),
                'projects_manage' => $projectsManage,
                'tasks_manage' => $tasksManage,
                'templates_manage' => $templatesManage,
                'teams_manage' => $teamsManage,
                'users_manage' => $usersManage,
                'roles_manage' => $rolesManage,
            ];
            return [
                'reference' => $repository->referenceData($userId, $capabilities),
                'capabilities' => $capabilities,
            ];
        }), [$authenticated]);
        $router->get('/api/v1/workflow/templates', $endpoint(static fn () => ['templates' => $repository->templates()]), [$authenticated, $permission('templates.manage')]);
        $router->get('/api/v1/workflow/orders', $endpoint(static fn (Request $request) => ['orders' => $repository->orders([
            'status' => $request->query('status', ''),
            'priority_id' => $request->query('priority_id', ''),
            'customer' => $request->query('customer', ''),
            'weight' => $request->query('weight', ''),
            'search' => $request->query('search', ''),
            'archived' => $request->query('archived', 'exclude'),
        ], $actor(), $mayManageProjects($actor()))]), [$authenticated]);
        $router->get('/api/v1/workflow/orders/{id}', $endpoint(static function (Request $request, array $params) use ($repository, $actor, $mayManageProjects): array {
            $order = $repository->order((int) ($params['id'] ?? 0), $actor(), $mayManageProjects($actor()));
            if ($order === null) throw new RuntimeException('سفارش پیدا نشد.');
            return ['order' => $order];
        }), [$authenticated]);
        $router->get('/api/v1/workflow/projects', $endpoint(static fn (Request $request) => ['projects' => $repository->projects([
            'status' => $request->query('status', ''),
            'priority_id' => $request->query('priority_id', ''),
            'customer' => $request->query('customer', ''),
            'search' => $request->query('search', ''),
            'archived' => $request->query('archived', 'exclude'),
        ], $actor(), $mayManageProjects($actor()))]), [$authenticated]);
        $router->get('/api/v1/workflow/projects/{id}', $endpoint(static function (Request $request, array $params) use ($repository, $actor, $mayManageProjects): array {
            $project = $repository->order((int) ($params['id'] ?? 0), $actor(), $mayManageProjects($actor()));
            if ($project === null) throw new RuntimeException('پروژه پیدا نشد.');
            return ['project' => $project];
        }), [$authenticated]);
        $router->get('/api/v1/workflow/tasks', $endpoint(static fn (Request $request) => ['tasks' => $repository->tasks($actor(), $mayManageTasks($actor()), [
            'status' => $request->query('status', ''),
            'task_type_id' => $request->query('task_type_id', ''),
            'order_id' => $request->query('order_id', ''),
            'customer' => $request->query('customer', ''),
            'archived' => $request->query('archived', 'exclude'),
        ])]), [$authenticated]);
        $router->get('/api/v1/workflow/tasks/{id}', $endpoint(static function (Request $request, array $params) use ($repository, $actor, $mayManageTasks): array {
            $userId = $actor();
            $task = $repository->task((int) ($params['id'] ?? 0), $userId, $mayManageTasks($userId));
            if ($task === null) throw new RuntimeException('تسک پیدا نشد یا به آن دسترسی ندارید.');
            return ['task' => $task];
        }), [$authenticated]);
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
        $router->post('/api/v1/workflow/projects/{id}/activate', $endpoint(static function (Request $request, array $params) use ($engine, $actor): array {
            $engine->activateOrder((int) ($params['id'] ?? 0), $actor());
            return [];
        }), [$authenticated, $csrf, $permission('orders.manage')]);
        $router->post('/api/v1/workflow/projects/{id}/stages', $endpoint(static fn (Request $request, array $params) => ['id' => $engine->addOrderStep((int) ($params['id'] ?? 0), $actor(), $request->all())], 201), [$authenticated, $csrf, $permission('orders.manage')]);

        $router->post('/api/v1/workflow/tasks', $endpoint(static fn (Request $request) => ['id' => $repository->createStandaloneTask($request->all(), $actor())], 201), [$authenticated, $csrf, $permission('tasks.manage')]);
        $router->post('/api/v1/workflow/tasks/{id}', $endpoint(static function (Request $request, array $params) use ($repository, $actor): array {
            $repository->updateStandaloneTask((int) ($params['id'] ?? 0), $request->all(), $actor());
            return [];
        }), [$authenticated, $csrf, $permission('tasks.manage')]);
        $router->post('/api/v1/workflow/tasks/{id}/delete', $endpoint(static function (Request $request, array $params) use ($repository): array {
            $repository->deleteStandaloneTask((int) ($params['id'] ?? 0));
            return [];
        }), [$authenticated, $csrf, $permission('tasks.manage')]);
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
        $router->post('/api/v1/workflow/tasks/{id}/archive', $endpoint(static function (Request $request, array $params) use ($repository, $actor): array {
            $repository->archiveTask((int) ($params['id'] ?? 0), $actor());
            return [];
        }), [$authenticated, $csrf, $permission('tasks.manage')]);
        $router->post('/api/v1/workflow/tasks/{id}/restore', $endpoint(static function (Request $request, array $params) use ($repository, $actor): array {
            $repository->restoreTask((int) ($params['id'] ?? 0), $actor());
            return [];
        }), [$authenticated, $csrf, $permission('tasks.manage')]);
        $router->post('/api/v1/workflow/notifications/read', $endpoint(static function () use ($repository, $actor): array {
            $repository->markNotificationsRead($actor());
            return [];
        }), [$authenticated, $csrf]);

        $router->post('/api/v1/workflow/task-types', $endpoint(static fn (Request $request) => ['id' => $repository->createTaskType((string) $request->input('name', ''), (string) $request->input('slug', ''), (string) $request->input('color', '#3157d5'), $request->input('description'))], 201), [$authenticated, $csrf, $permission('templates.manage')]);
        $router->post('/api/v1/workflow/task-types/{id}', $endpoint(static function (Request $request, array $params) use ($repository): array {
            $repository->updateTaskType((int) ($params['id'] ?? 0), $request->all());
            return [];
        }), [$authenticated, $csrf, $permission('templates.manage')]);
        $router->post('/api/v1/workflow/task-types/{id}/delete', $endpoint(static function (Request $request, array $params) use ($repository): array {
            $repository->deleteTaskType((int) ($params['id'] ?? 0));
            return [];
        }), [$authenticated, $csrf, $permission('templates.manage')]);
        $router->post('/api/v1/workflow/priorities', $endpoint(static fn (Request $request) => ['id' => $repository->createPriority((string) $request->input('key_name', ''), (string) $request->input('name', ''), (string) $request->input('color', '#64748b'), (int) $request->input('sort_order', 0))], 201), [$authenticated, $csrf, $permission('orders.manage')]);
        $router->post('/api/v1/workflow/customers', $endpoint(static fn (Request $request) => ['id' => $repository->createCustomer((string) $request->input('name', ''), $request->input('phone'), $request->input('email'), $request->input('notes'))], 201), [$authenticated, $csrf, $permission('orders.manage')]);
        $router->post('/api/v1/workflow/customers/{id}', $endpoint(static function (Request $request, array $params) use ($repository): array {
            $repository->updateCustomer((int) ($params['id'] ?? 0), $request->all());
            return [];
        }), [$authenticated, $csrf, $permission('orders.manage')]);
        $router->post('/api/v1/workflow/customers/{id}/delete', $endpoint(static function (Request $request, array $params) use ($repository): array {
            $repository->deleteCustomer((int) ($params['id'] ?? 0));
            return [];
        }), [$authenticated, $csrf, $permission('orders.manage')]);
        $router->post('/api/v1/workflow/teams', $endpoint(static fn (Request $request) => ['id' => $repository->createTeam((string) $request->input('name', ''), $request->input('description'), $actor())], 201), [$authenticated, $csrf, $permission('teams.manage')]);
        $router->post('/api/v1/workflow/teams/{id}/members', $endpoint(static function (Request $request, array $params) use ($repository): array {
            $repository->addTeamMember((int) ($params['id'] ?? 0), (int) $request->input('user_id', 0), (bool) $request->input('is_lead', false));
            return [];
        }), [$authenticated, $csrf, $permission('teams.manage')]);
        $router->post('/api/v1/workflow/projects', $endpoint(static fn (Request $request) => ['id' => $repository->createWorkflowProject($request->all(), $actor())], 201), [$authenticated, $csrf, $permission('orders.manage')]);
        $router->post('/api/v1/workflow/projects/{id}', $endpoint(static function (Request $request, array $params) use ($repository, $actor): array {
            $repository->updateWorkflowProject((int) ($params['id'] ?? 0), $request->all(), $actor());
            return [];
        }), [$authenticated, $csrf, $permission('orders.manage')]);
        $router->post('/api/v1/workflow/projects/{id}/members', $endpoint(static function (Request $request, array $params) use ($repository, $actor): array {
            $repository->addWorkflowProjectMember((int) ($params['id'] ?? 0), (int) $request->input('user_id', 0), $request->input('role_label'), $actor());
            return [];
        }), [$authenticated, $csrf, $permission('orders.manage')]);
        $router->post('/api/v1/workflow/projects/{id}/members/remove', $endpoint(static function (Request $request, array $params) use ($repository, $actor): array {
            $repository->removeWorkflowProjectMember((int) ($params['id'] ?? 0), (int) $request->input('user_id', 0), $actor());
            return [];
        }), [$authenticated, $csrf, $permission('orders.manage')]);
        $router->post('/api/v1/workflow/projects/{id}/archive', $endpoint(static function (Request $request, array $params) use ($repository, $actor): array {
            $repository->archiveWorkflowProject((int) ($params['id'] ?? 0), $actor());
            return [];
        }), [$authenticated, $csrf, $permission('orders.manage')]);
        $router->post('/api/v1/workflow/projects/{id}/restore', $endpoint(static function (Request $request, array $params) use ($repository, $actor): array {
            $repository->restoreWorkflowProject((int) ($params['id'] ?? 0), $actor());
            return [];
        }), [$authenticated, $csrf, $permission('orders.manage')]);
        $router->post('/api/v1/workflow/projects/{id}/delete', $endpoint(static function (Request $request, array $params) use ($repository): array {
            $repository->deleteWorkflowProject((int) ($params['id'] ?? 0));
            return [];
        }), [$authenticated, $csrf, $systemAdmin]);
        $router->post('/api/v1/workflow/templates/{id}/delete', $endpoint(static function (Request $request, array $params) use ($repository): array {
            $repository->deleteWorkflowTemplate((int) ($params['id'] ?? 0));
            return [];
        }), [$authenticated, $csrf, $systemAdmin]);
        $router->post('/api/v1/workflow/admin/wipe', $endpoint(static function (Request $request) use ($repository): array {
            if ((string) $request->input('confirmation', '') !== 'WIPE') {
                throw new RuntimeException('عبارت تأیید WIPE صحیح نیست.');
            }
            $repository->wipeTestData();
            return [];
        }), [$authenticated, $csrf, $systemAdmin]);
        $router->post('/api/v1/workflow/users', $endpoint(static fn (Request $request) => ['id' => $repository->createUser($request->all(), $actor())], 201), [$authenticated, $csrf, $permission('users.manage')]);
        $router->post('/api/v1/workflow/users/{id}', $endpoint(static function (Request $request, array $params) use ($repository, $actor): array {
            $repository->updateUser((int) ($params['id'] ?? 0), $request->all(), $actor());
            return [];
        }), [$authenticated, $csrf, $permission('users.manage')]);
        $router->post('/api/v1/workflow/users/{id}/password', $endpoint(static function (Request $request, array $params) use ($repository, $actor): array {
            $repository->resetUserPassword((int) ($params['id'] ?? 0), (string) $request->input('password', ''), $actor());
            return [];
        }), [$authenticated, $csrf, $permission('users.manage')]);
        $router->post('/api/v1/workflow/roles', $endpoint(static fn (Request $request) => ['id' => $repository->createRole((string) $request->input('key_name', ''), (string) $request->input('display_name', ''), (array) $request->input('permissions', []), $actor(), (bool) $request->input('is_active', true))], 201), [$authenticated, $csrf, $permission('roles.manage')]);
        $router->post('/api/v1/workflow/roles/{id}', $endpoint(static function (Request $request, array $params) use ($repository, $actor): array {
            $repository->updateRole((int) ($params['id'] ?? 0), $request->all(), $actor());
            return [];
        }), [$authenticated, $csrf, $permission('roles.manage')]);
        $router->post('/api/v1/workflow/roles/{id}/clone', $endpoint(static fn (Request $request, array $params) => ['id' => $repository->cloneRole((int) ($params['id'] ?? 0), (string) $request->input('key_name', ''), (string) $request->input('display_name', ''), $actor(), (array) $request->input('permissions', []), (bool) $request->input('is_active', true))], 201), [$authenticated, $csrf, $permission('roles.manage')]);
        $router->post('/api/v1/workflow/roles/{id}/delete', $endpoint(static function (Request $request, array $params) use ($repository, $actor): array {
            $repository->deleteRole((int) ($params['id'] ?? 0), $actor());
            return [];
        }), [$authenticated, $csrf, $permission('roles.manage')]);
        $router->post('/api/v1/workflow/users/{id}/roles', $endpoint(static function (Request $request, array $params) use ($repository, $actor): array {
            $repository->replaceUserRoles((int) ($params['id'] ?? 0), (array) $request->input('role_ids', []), $actor());
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
        $router->post('/api/v1/workflow/projects/{id}/attachments', $endpoint(static function (Request $request, array $params) use ($repository, $actor): array {
            $file = $request->file('image');
            if ($file === null) throw new RuntimeException('تصویر انتخاب نشده است.');
            $upload = new UploadService();
            $path = $upload->storeImage($file, 'projects');
            try {
                return ['id' => $repository->attachOrderImage((int) ($params['id'] ?? 0), $actor(), $path, (string) ($file['name'] ?? ''), (string) ($file['type'] ?? 'application/octet-stream'), (int) ($file['size'] ?? 0), $request->input('caption'))];
            } catch (\Throwable $exception) {
                $upload->delete($path);
                throw $exception;
            }
        }, 201), [$authenticated, $csrf, $permission('orders.manage')]);
    }
}
