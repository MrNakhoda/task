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
