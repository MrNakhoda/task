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
    foreach (['/projects', '/projects/{id}/activate', '/projects/{id}/delete', '/tasks', '/tasks/{id}/complete', '/templates/{id}/steps', '/templates/{id}/delete', '/admin/wipe', '/teams/{id}/members', '/users', '/notifications'] as $route) {
        $assert(str_contains($source, $route), 'Missing API route: ' . $route);
    }
    $assert(str_contains($source, 'createWorkflowProject'));
    $assert(str_contains($source, 'createStandaloneTask'));
    $assert(str_contains($source, '$systemAdmin'));
});

$test('admin wipe preserves accounts and access control', static function () use ($assert): void {
    $source = file_get_contents(APP_ROOT . '/src/Modules/Workflow/WorkflowRepository.php');
    $assert(is_string($source) && str_contains($source, 'wipeTestData'));
    $start = strpos($source, 'public function wipeTestData');
    $end = strpos($source, 'public function createCustomer', $start);
    $wipe = substr($source, $start, $end - $start);
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
