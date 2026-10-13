<?php

declare(strict_types=1);

// Run only against a disposable test database. Every run uses separate tables.
if (getenv('APP_ENV') !== 'testing' || !getenv('DB_NAME')) {
    fwrite(STDERR, "APP_ENV=testing and a test DB_NAME are required.\n");
    exit(1);
}
const APP_ROOT = __DIR__ . '/..';
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) require APP_ROOT . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
});
use App\Database\Connection;
use App\Database\Migrator;
use App\Core\ModuleRegistry;
use App\Modules\Workflow\WorkflowEngine;
use App\Modules\Workflow\WorkflowRepository;
use App\Support\Table;

$prefix = 'wft_' . bin2hex(random_bytes(5)) . '_';
$_ENV['DB_TABLE_PREFIX'] = $prefix;
$_SERVER['DB_TABLE_PREFIX'] = $prefix;
Connection::configure(['host' => getenv('DB_HOST') ?: '127.0.0.1', 'port' => getenv('DB_PORT') ?: '3306', 'name' => getenv('DB_NAME'), 'user' => getenv('DB_USER') ?: 'root', 'pass' => getenv('DB_PASS') ?: '']);
$pdo = Connection::get();
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$insert = static function (string $table, array $data) use ($pdo): int {
    $columns = implode(',', array_keys($data));
    $values = implode(',', array_fill(0, count($data), '?'));
    $pdo->prepare('INSERT INTO ' . Table::name($table) . " ({$columns}) VALUES ({$values})")->execute(array_values($data));
    return (int) $pdo->lastInsertId();
};
$reject = static function (callable $action) use ($assert): void {
    $failed = false;
    try { $action(); } catch (RuntimeException $exception) { $failed = true; }
    $assert($failed, 'Invalid operation accepted');
};
try {
    $migrator = new Migrator(new ModuleRegistry(require APP_ROOT . '/config/modules.php'));
    $migrator->migrate(['workflow']);
    $assert($migrator->migrate(['workflow']) === [], 'Migrations are not idempotent');
    $repo = new WorkflowRepository();
    $engine = new WorkflowEngine($repo);
    $actor = $insert('users', ['name' => 'Test manager', 'email' => 'manager@example.test', 'password_hash' => 'test']);
    $worker = $insert('users', ['name' => 'Test worker', 'email' => 'worker@example.test', 'password_hash' => 'test']);
    $team = $repo->createTeam('Test team', null, $actor);
    $repo->addTeamMember($team, $worker, false, $actor);
    $type = $repo->createTaskType('Test type', 'test-type', '#3157d5', null);
    $template = $repo->createTemplate('Original', 'Description', $actor);
    $stepData = ['name' => 'First', 'task_type_id' => $type, 'team_id' => $team];
    $first = $repo->addTemplateStep($template, $stepData);
    $second = $repo->addTemplateStep($template, [...$stepData, 'name' => 'Second', 'dependency_ids' => [$first]]);
    $middle = $repo->addTemplateStep($template, [...$stepData, 'name' => 'Middle', 'placement' => 'before', 'anchor_step_id' => $second]);
    $assert(array_column($repo->template($template)['steps'], 'id') === [$first, $middle, $second], 'Insert between template stages failed');
    $file = $insert('files', ['owner_id' => $actor, 'disk_name' => 'private', 'path' => 'test-guide.pdf', 'original_name' => 'guide.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1]);
    $insert('workflow_template_attachments', ['workflow_template_id' => $template, 'file_id' => $file, 'title' => 'Guide', 'sort_order' => 10, 'uploaded_by' => $actor]);
    $copyTemplate = $repo->cloneWorkflowTemplate($template, 'Copy template', $actor);
    $copy = $repo->template($copyTemplate);
    $assert(count($copy['steps']) === 3 && count($copy['attachments']) === 1 && (int) $copy['version'] === 1, 'Template copy lost data');
    $copyFirst = $copy['steps'][0]['id'];
    $copySecond = $copy['steps'][2]['id'];
    $assert($copy['steps'][2]['dependencies'] === [$copyFirst], 'Template dependencies were not remapped');
    $assert($copy['steps'][0]['team_ids'] === [$team], 'Team assignment was not copied');
    $repo->reorderTemplateSteps($copyTemplate, $actor, [$copySecond, $copyFirst, $copy['steps'][1]['id']]);
    $reject(fn () => $repo->reorderTemplateSteps($copyTemplate, $actor, [$copyFirst, $first]));
    $reject(fn () => $repo->reorderTemplateSteps($copyTemplate, $actor, [$copyFirst, $copyFirst, $copySecond]));
    $reject(fn () => $repo->addTemplateStep($copyTemplate, [...$stepData, 'placement' => 'before', 'anchor_step_id' => $first]));
    $assert(array_column($repo->template($template)['steps'], 'id') === [$first, $middle, $second], 'Changing copy affected original template');
    echo "PASS template copy, remapped dependencies, guide isolation, insertion, exact reorder\n";

    $project = $repo->createWorkflowProject(['name' => 'Original project', 'workflow_template_id' => $template, 'description' => 'Project description', 'due_at' => '2030-01-01'], $actor);
    $engine->activateOrder($project, $actor);
    $original = $repo->order($project);
    $firstStage = $original['steps'][0];
    $secondStage = $original['steps'][2];
    $added = $engine->addOrderStep($project, $actor, ['name' => 'Custom stage', 'task_type_id' => $type, 'user_ids' => [$worker], 'placement' => 'before', 'anchor_step_id' => $secondStage['id'], 'dependency_ids' => [$firstStage['id']]]);
    $engine->startTask((int) $firstStage['task_id'], $actor, true);
    $engine->completeTask((int) $firstStage['task_id'], $actor, true, 'Original report');
    $clonedProject = $repo->cloneWorkflowProject($project, 'Copy project', $actor);
    $cloned = $repo->order($clonedProject);
    $assert($cloned['status'] === 'draft' && (float) $cloned['progress_percent'] === 0.0 && $cloned['due_at'] === null && $cloned['activated_at'] === null && $cloned['completed_at'] === null, 'Project execution state was copied');
    $assert($cloned['reports'] === [] && $cloned['attachments'] === [] && count($cloned['steps']) === 4 && count($cloned['template_attachments']) === 1, 'Raw project copy lost structure or copied execution data');
    $assert($cloned['order_number'] !== $original['order_number'] && $cloned['project_id'] !== $original['project_id'], 'Project identifiers were reused');
    foreach ($cloned['steps'] as $step) $assert($step['status'] === 'pending' && $step['task_id'] === null && $step['activated_at'] === null, 'Copied step already started');
    $cloneIds = array_column($cloned['steps'], 'id');
    $engine->setStepDependencies($cloneIds[1], $actor, [$cloneIds[0]]);
    $assert(array_filter(array_column($repo->order($clonedProject)['steps'], 'task_id')) === [], 'Editing draft created tasks');
    $engine->reorderSteps($clonedProject, $actor, array_reverse($cloneIds));
    $reject(fn () => $engine->reorderSteps($clonedProject, $actor, [$cloneIds[0]]));
    $reject(fn () => $engine->reorderSteps($clonedProject, $actor, [$cloneIds[0], $cloneIds[1], $cloneIds[2], $firstStage['id']]));
    $assert(array_column($repo->order($clonedProject)['steps'], 'id') === array_reverse($cloneIds), 'Invalid order changed stored positions');
    $repo->disableTemplateStep($first);
    $repo->deleteTemplateAttachment($repo->template($template)['attachments'][0]['id'], $actor);
    $engine->activateOrder($clonedProject, $actor);
    $started = $repo->order($clonedProject);
    $assert(count($started['steps']) === 4 && count($started['template_attachments']) === 1, 'Starting copy rebuilt stages/guides from changed template');
    $assert(in_array('Custom stage', array_column($started['steps'], 'name'), true), 'Custom stage lost on activation');
    $assert(count($started['members']) >= 1, 'Copied project members were lost');
    $assert(array_column($repo->order($project)['steps'], 'id') !== array_column($started['steps'], 'id'), 'Project steps shared IDs');
    foreach ($repo->order($project)['steps'] as $step) {
        if ($step['task_status'] === 'open') $engine->startTask((int) $step['task_id'], $actor, true);
        if (in_array($step['task_status'], ['open', 'in_progress'], true)) $engine->completeTask((int) $step['task_id'], $actor, true);
    }
    $repo->archiveWorkflowProject($project, $actor);
    $archivedCopy = $repo->order($repo->cloneWorkflowProject($project, 'Archived copy', $actor));
    $assert($archivedCopy['archived_at'] === null && $archivedCopy['status'] === 'draft' && $archivedCopy['reports'] === [], 'Archived project copy did not reset');
    $reject(fn () => $engine->reorderSteps($project, $actor, array_column($repo->order($project)['steps'], 'id')));
    $assert(count($repo->template($copyTemplate)['attachments']) === 1, 'Deleting original guide affected copied template');
    $reject(fn () => $repo->cloneWorkflowProject($project, '', $actor));
    $reject(fn () => $repo->cloneWorkflowTemplate($template, '', $actor));
    echo "PASS raw project reset, draft edit safety, activation snapshot, project order isolation\n";
    echo "Workflow structure integration tests passed.\n";
} finally {
    // Delete only tables created by this randomly prefixed test run.
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($tables as $table) {
        if (str_starts_with($table, $prefix)) $pdo->exec('DROP TABLE `' . $table . '`');
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
}
