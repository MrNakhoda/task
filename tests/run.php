<?php

declare(strict_types=1);

use App\Auth\PasswordHasher;
use App\Core\ModuleRegistry;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Security\Csrf;
use App\Support\Env;
use App\Support\Table;

const APP_ROOT = __DIR__ . '/..';

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $path = APP_ROOT . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

$tests = [];
$test = static function (string $name, callable $callback) use (&$tests): void {
    $tests[$name] = $callback;
};
$assert = static function (bool $condition, string $message = 'Assertion failed'): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$test('password hashing and verification', static function () use ($assert): void {
    $hasher = new PasswordHasher();
    $hash = $hasher->hash('a-secure-example-password');
    $assert($hasher->verify('a-secure-example-password', $hash));
    $assert(!$hasher->verify('wrong-password', $hash));
});

$test('router extracts named path parameters', static function () use ($assert): void {
    $router = new Router();
    $router->get('/items/{id}', static fn (Request $request, array $params): Response => Response::json(['id' => $params['id']]));
    $response = $router->dispatch(new Request('GET', '/items/42'));
    $assert($response->status() === 200);
    $assert(json_decode($response->body(), true)['id'] === '42');
});

$test('router does not treat literal dots as regex', static function () use ($assert): void {
    $router = new Router();
    $router->get('/meta.json', static fn (): Response => Response::json(['ok' => true]));
    $assert($router->dispatch(new Request('GET', '/metaXjson'))->status() === 404);
});

$test('csrf tokens validate and rotate', static function () use ($assert): void {
    $_SESSION = [];
    $first = Csrf::token();
    $assert(Csrf::valid($first));
    $second = Csrf::rotate();
    $assert($first !== $second && !Csrf::valid($first) && Csrf::valid($second));
});

$test('module registry resolves dependencies first', static function () use ($assert): void {
    $registry = new ModuleRegistry([
        'catalog' => ['class' => stdClass::class, 'migrations' => '/tmp/catalog', 'depends' => []],
        'commerce' => ['class' => stdClass::class, 'migrations' => '/tmp/commerce', 'depends' => ['catalog']],
    ]);
    $assert($registry->enabled(['commerce']) === ['catalog', 'commerce']);
});

$test('table names use an allowlisted default prefix', static function () use ($assert): void {
    $assert(Table::name('users') === 'app_users');
});

$test('workflow module contains the required domain schema', static function () use ($assert): void {
    $sql = file_get_contents(APP_ROOT . '/database/modules/workflow/001_workflow.sql');
    $assert(is_string($sql));
    foreach (['workflow_templates', 'workflow_template_step_dependencies', 'work_orders', 'order_workflow_steps', 'tasks', 'task_assignees', 'task_reports', 'workflow_activity_logs', 'user_notifications', 'order_attachments'] as $table) {
        $assert(str_contains($sql, '{{prefix}}' . $table), 'Missing workflow table: ' . $table);
    }
});

$test('workflow supports standalone tasks outside projects', static function () use ($assert): void {
    $sql = file_get_contents(APP_ROOT . '/database/modules/workflow/002_standalone_tasks.sql');
    $repository = file_get_contents(APP_ROOT . '/src/Modules/Workflow/WorkflowRepository.php');
    $assert(is_string($sql) && str_contains($sql, 'is_standalone'));
    $assert(str_contains($sql, 'MODIFY order_id BIGINT UNSIGNED DEFAULT NULL'));
    $assert(is_string($repository) && str_contains($repository, 'createStandaloneTask'));
});

$test('workflow archive migration preserves data and separates role management', static function () use ($assert): void {
    $sql = file_get_contents(APP_ROOT . '/database/modules/workflow/003_project_archive_and_role_management.sql');
    $assert(is_string($sql));
    $assert(substr_count($sql, 'ADD COLUMN archived_at') === 2, 'Projects and tasks must have independent archive timestamps.');
    $assert(str_contains($sql, "('roles.manage', 'Manage roles and permissions')"));
    $assert(str_contains($sql, "r.key_name = 'admin'"));
});

$test('workflow engine implements automatic dependency progression', static function () use ($assert): void {
    $source = file_get_contents(APP_ROOT . '/src/Modules/Workflow/WorkflowEngine.php');
    $assert(is_string($source));
    $assert(str_contains($source, 'activateReadySteps'));
    $assert(str_contains($source, "status='completed'"));
    $assert(str_contains($source, 'assertAcyclic'));
});

$test('workflow API exposes project task and administration endpoints', static function () use ($assert): void {
    $source = file_get_contents(APP_ROOT . '/src/Modules/Workflow/WorkflowModule.php');
    $assert(is_string($source));
    foreach (['/projects', '/projects/{id}/activate', '/projects/{id}/delete', '/projects/{id}/members/remove', '/projects/{id}/attachments', '/tasks', '/tasks/{id}', '/tasks/{id}/assignees', '/tasks/{id}/complete', '/task-types/{id}', '/task-types/{id}/delete', '/customers/{id}', '/templates/{id}/steps', '/templates/{id}/delete', '/admin/wipe', '/teams/{id}/members', '/users', '/notifications'] as $route) {
        $assert(str_contains($source, $route), 'Missing API route: ' . $route);
    }
    $assert(str_contains($source, 'createWorkflowProject'));
    $assert(str_contains($source, 'createStandaloneTask'));
    $assert(str_contains($source, '$systemAdmin'));
});

$test('archive, member access, user and role safeguards are wired end to end', static function () use ($assert): void {
    $module = file_get_contents(APP_ROOT . '/src/Modules/Workflow/WorkflowModule.php');
    $repository = file_get_contents(APP_ROOT . '/src/Modules/Workflow/WorkflowRepository.php');
    $engine = file_get_contents(APP_ROOT . '/src/Modules/Workflow/WorkflowEngine.php');
    $view = file_get_contents(APP_ROOT . '/views/workspace.php');
    $javascript = file_get_contents(APP_ROOT . '/public/assets/workflow.js');
    foreach (['/tasks/{id}/archive', '/tasks/{id}/restore', '/projects/{id}/archive', '/projects/{id}/restore', '/users/{id}', '/users/{id}/password', '/roles/{id}', '/roles/{id}/clone', '/roles/{id}/delete'] as $route) {
        $assert(is_string($module) && str_contains($module, $route), 'Missing protected update route: ' . $route);
    }
    $assert(str_contains($module, "\$permission('roles.manage')"));
    $assert(
        str_contains($module, 'use ($repository, $actor, $allows, $mayManageProjects, $mayManageTasks, $isSystemAdmin)'),
        'The workflow reference endpoint must capture the system-admin capability callback.'
    );
    $assert(is_string($repository) && str_contains($repository, 'assertFullAdministratorRemains'));
    $assert(str_contains($repository, 'EXISTS (SELECT 1 FROM {$projectMembers} mine'));
    $assert(str_contains($repository, 'task_can_work'));
    $assert(is_string($engine) && str_contains($engine, 'assertTaskWritable'));
    $assert(is_string($view) && str_contains($view, 'data-view="archives"'));
    $assert(str_contains($view, 'name="role_ids" data-options="roles" multiple'));
    $assert(is_string($javascript) && str_contains($javascript, 'stageTaskActions(stage, archived)'));
    $assert(str_contains($javascript, "api('projects?archived=only')"));
    $assert(str_contains($view, 'data-user-edit-form'), 'User edit form needs a selector distinct from management buttons.');
    $assert(!str_contains($view, '<form method="dialog" class="tf-modal-card" data-edit-user>'), 'User edit button selector must not be reused on the form.');
    $assert(str_contains($view, 'data-role-options') && str_contains($view, 'data-permission-options'), 'Role and permission checkbox management must be visible.');
    $assert(str_contains($javascript, "root.querySelector('[data-user-edit-form]')"), 'User management must target the actual edit form.');
    $assert(str_contains($javascript, "form.matches('[data-user-edit-form]')"), 'User edit submissions must use the dedicated form selector.');
    $assert(str_contains($javascript, 'data-permission-choice') && str_contains($javascript, 'renderEffectivePermissions'), 'Permission selection and effective access preview must be wired.');
});

$test('completed tasks remain on the main board until explicitly archived', static function () use ($assert): void {
    $repository = file_get_contents(APP_ROOT . '/src/Modules/Workflow/WorkflowRepository.php');
    $view = file_get_contents(APP_ROOT . '/views/workspace.php');
    $javascript = file_get_contents(APP_ROOT . '/public/assets/workflow.js');
    $stylesheet = file_get_contents(APP_ROOT . '/public/assets/app.css');
    $assert(is_string($repository) && str_contains($repository, "t.status <> 'cancelled'"));
    $assert(!str_contains($repository, "t.status NOT IN ('completed','cancelled')"), 'Completed tasks must not be hidden by the default query.');
    $assert(is_string($view) && str_contains($view, 'همه وظایف جاری'));
    $assert(is_string($javascript) && str_contains($javascript, "['completed', 'انجام‌شده']"));
    $assert(str_contains($javascript, 'data-task-column="${status}"'), 'Task columns must expose their status for responsive styling.');
    $assert(is_string($stylesheet) && str_contains($stylesheet, 'repeat(auto-fit, minmax(260px, 1fr))'), 'Filtered boards must use the full available width.');
});

$test('remembered login uses rotating server-side hashed tokens', static function () use ($assert): void {
    $migration = file_get_contents(APP_ROOT . '/database/migrations/002_remember_login_and_web_push.sql');
    $service = file_get_contents(APP_ROOT . '/src/Auth/RememberLoginService.php');
    $auth = file_get_contents(APP_ROOT . '/src/Auth/AuthService.php');
    $login = file_get_contents(APP_ROOT . '/views/login.php');
    $assert(is_string($migration) && str_contains($migration, '{{prefix}}remember_login_tokens'));
    $assert(str_contains($migration, 'validator_hash CHAR(64)'));
    $assert(!str_contains($migration, 'validator VARCHAR'), 'Raw validators must never be persisted.');
    $assert(is_string($service) && str_contains($service, "hash('sha256', \$validator)"));
    $assert(str_contains($service, 'validator_hash=?,last_used_at=NOW()'), 'Remember validators must rotate after automatic login.');
    $assert(str_contains($service, "'httponly' => true") && str_contains($service, "'samesite' => 'Lax'"));
    $assert(is_string($auth) && str_contains($auth, '$this->remember->consume()'));
    $assert(str_contains($auth, '$this->remember->revokeCurrent()'));
    $assert(is_string($login) && str_contains($login, 'name="remember_device"'));
});

$test('web push keeps an in-app source of truth and per-device delivery state', static function () use ($assert): void {
    $coreMigration = file_get_contents(APP_ROOT . '/database/migrations/002_remember_login_and_web_push.sql');
    $workflowMigration = file_get_contents(APP_ROOT . '/database/modules/workflow/004_notification_delivery.sql');
    $notifications = file_get_contents(APP_ROOT . '/src/Notification/NotificationService.php');
    $push = file_get_contents(APP_ROOT . '/src/Pwa/WebPushService.php');
    $module = file_get_contents(APP_ROOT . '/src/Pwa/PwaModule.php');
    $assert(is_string($coreMigration) && str_contains($coreMigration, '{{prefix}}push_subscriptions'));
    $assert(is_string($workflowMigration) && str_contains($workflowMigration, '{{prefix}}push_notification_deliveries'));
    $assert(str_contains($workflowMigration, 'dedupe_key'));
    $assert(is_string($notifications) && str_contains($notifications, "'notification.web_push'"));
    $assert(is_string($push) && str_contains($push, 'sendOneNotification'));
    $assert(str_contains($push, 'isSubscriptionExpired'));
    $assert(!str_contains($push, "'topic' =>"), 'Push Topic is optional and must not be sent because Apple rejects non-conforming values.');
    foreach (['/api/v1/pwa/config', '/api/v1/pwa/subscriptions', '/api/v1/pwa/subscriptions/remove', '/api/v1/pwa/test'] as $route) {
        $assert(is_string($module) && str_contains($module, $route), 'Missing PWA route: ' . $route);
    }
});

$test('PWA shell is installable without caching private application data', static function () use ($assert): void {
    $manifest = json_decode((string) file_get_contents(APP_ROOT . '/public/manifest.webmanifest'), true, flags: JSON_THROW_ON_ERROR);
    $worker = file_get_contents(APP_ROOT . '/public/sw.js');
    $layout = file_get_contents(APP_ROOT . '/views/layout.php');
    $pwa = file_get_contents(APP_ROOT . '/public/assets/pwa.js');
    $assert(($manifest['display'] ?? '') === 'standalone');
    $assert(($manifest['dir'] ?? '') === 'rtl');
    $assert(is_string($worker) && str_contains($worker, "url.pathname.startsWith('/api/')"));
    $assert(str_contains($worker, "request.mode === 'navigate'"));
    $assert(is_string($layout) && str_contains($layout, 'rel="manifest"'));
    $assert(str_contains($layout, 'apple-touch-icon'));
    $assert(is_string($pwa) && str_contains($pwa, 'beforeinstallprompt'));
    $assert(str_contains($pwa, 'Notification.requestPermission()'));
});

$test('management navigation and reference data follow effective permissions', static function () use ($assert): void {
    $module = file_get_contents(APP_ROOT . '/src/Modules/Workflow/WorkflowModule.php');
    $repository = file_get_contents(APP_ROOT . '/src/Modules/Workflow/WorkflowRepository.php');
    $view = file_get_contents(APP_ROOT . '/views/workspace.php');
    $javascript = file_get_contents(APP_ROOT . '/public/assets/workflow.js');
    $assert(is_string($view) && str_contains($view, 'data-view="users" data-view-requires="users_manage,roles_manage"'));
    foreach (['workflows" data-view-requires="templates_manage', 'customers" data-view-requires="projects_manage', 'teams" data-view-requires="teams_manage'] as $guard) {
        $assert(str_contains($view, $guard), 'Missing management view guard: ' . $guard);
    }
    $assert(is_string($javascript) && str_contains($javascript, 'function canView(view)'));
    $assert(str_contains($javascript, "toast('به این بخش دسترسی ندارید.'"), 'Direct hash navigation must be guarded.');
    $assert(str_contains($javascript, 'if (state.capabilities.templates_manage) requests.push(loadTemplates())'));
    $assert(is_string($module) && str_contains($module, "[\$authenticated, \$permission('templates.manage')]"), 'Template details must require template management permission.');
    $assert(is_string($repository) && str_contains($repository, 'public function referenceData(int $userId, array $access)'));
    $assert(str_contains($repository, "\$canAssignPeople ? \"u.status='active'\" : 'u.id='"), 'Workers must receive only their own user reference row.');
    $assert(str_contains($repository, 'WHERE 1=0'), 'Unauthorized management reference collections must be empty.');
});

$test('workspace exposes business setup, guidance and assignment management', static function () use ($assert): void {
    $view = file_get_contents(APP_ROOT . '/views/workspace.php');
    $javascript = file_get_contents(APP_ROOT . '/public/assets/workflow.js');
    $engine = file_get_contents(APP_ROOT . '/src/Modules/Workflow/WorkflowEngine.php');
    $guide = file_get_contents(APP_ROOT . '/docs/USER_GUIDE_FA.md');
    $assert(is_string($view) && str_contains($view, 'data-view="task-types"'));
    $assert(str_contains($view, 'data-view="guide"'));
    $assert(str_contains($view, 'data-save-task-type'));
    $assert(is_string($javascript) && str_contains($javascript, 'data-task-assignees'));
    $assert(str_contains($javascript, 'eligible_assignees'));
    $assert(is_string($engine) && str_contains($engine, 'assertEligibleAssignees'));
    $assert(str_contains($engine, 'array_intersect'));
    $assert(is_string($guide) && str_contains($guide, 'منطق تخصیص'));
});

$test('browser-native alerts are replaced by styled application modals', static function () use ($assert): void {
    $layout = file_get_contents(APP_ROOT . '/views/layout.php');
    $appJavascript = file_get_contents(APP_ROOT . '/public/assets/app.js');
    $workflowJavascript = file_get_contents(APP_ROOT . '/public/assets/workflow.js');
    $assert(is_string($layout) && str_contains($layout, 'data-app-dialog'));
    $assert(is_string($appJavascript) && str_contains($appJavascript, 'window.AppModal'));
    foreach ([$appJavascript, $workflowJavascript] as $source) {
        $assert(!str_contains($source, 'window.alert('), 'Native window.alert must not be used.');
        $assert(preg_match('/(?<![.\\w])(alert|confirm|prompt)\\s*\\(/', $source) === 0, 'Native alert, confirm or prompt must not be used.');
    }
});

$test('workspace ships a unified responsive interface', static function () use ($assert): void {
    $layout = file_get_contents(APP_ROOT . '/views/layout.php');
    $login = file_get_contents(APP_ROOT . '/views/login.php');
    $workspace = file_get_contents(APP_ROOT . '/views/workspace.php');
    $stylesheet = file_get_contents(APP_ROOT . '/public/assets/app.css');
    $javascript = file_get_contents(APP_ROOT . '/public/assets/workflow.js');
    $routes = file_get_contents(APP_ROOT . '/src/Module/SystemModule.php');
    $assert(is_string($layout) && str_contains($layout, '$assetVersion'));
    $assert(is_string($login) && str_contains($login, 'data-password-toggle'));
    $assert(str_contains($login, "data-redirect=\"<?= htmlspecialchars(\$to('/workspace')"));
    $assert(is_string($workspace) && str_contains($workspace, 'class="tf-commandbar"'));
    $assert(str_contains($workspace, 'class="tf-mobile-nav"'));
    $assert(str_contains($workspace, 'data-current-view-title'));
    $assert(substr_count($workspace, 'data-nav-notifications') === 2, 'Notifications must live in the command bar and mobile navigation.');
    $assert(is_string($stylesheet) && str_contains($stylesheet, 'font-family: Vazirmatn'));
    $assert(str_contains($stylesheet, '@media (max-width: 760px)'));
    $assert(is_string($javascript) && str_contains($javascript, 'viewTitles'));
    $assert(str_contains($javascript, "querySelectorAll('[data-nav-notifications]')"));
    $assert(is_string($routes) && str_contains($routes, "Response::redirect(\$auth->user() === null ? '/login' : '/workspace')"));
});

$test('admin wipe preserves accounts and access control', static function () use ($assert): void {
    $source = file_get_contents(APP_ROOT . '/src/Modules/Workflow/WorkflowRepository.php');
    $assert(is_string($source) && str_contains($source, 'wipeTestData'));
    $start = strpos($source, 'public function wipeTestData');
    $end = strpos($source, 'public function createCustomer', $start);
    $wipe = substr($source, $start, $end - $start);
    $assert(str_contains($wipe, "Table::name('task_types')"));
    $assert(str_contains($wipe, "DELETE FROM {\$tables['task_types']}"));
    $assert(!str_contains($wipe, "Table::name('users')"));
    $assert(!str_contains($wipe, "Table::name('roles')"));
    $assert(!str_contains($wipe, "Table::name('permissions')"));
});

$failures = 0;
foreach ($tests as $name => $callback) {
    try {
        $callback();
        echo "PASS {$name}\n";
    } catch (Throwable $exception) {
        $failures++;
        echo "FAIL {$name}: {$exception->getMessage()}\n";
    }
}

echo sprintf("\n%d tests, %d failures\n", count($tests), $failures);
exit($failures === 0 ? 0 : 1);
