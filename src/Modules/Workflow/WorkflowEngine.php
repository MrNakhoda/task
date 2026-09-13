<?php

declare(strict_types=1);

namespace App\Modules\Workflow;

use App\Database\Connection;
use App\Support\Table;
use PDO;
use RuntimeException;

final class WorkflowEngine
{
    public function __construct(private readonly WorkflowRepository $repository = new WorkflowRepository())
    {
    }

    public function activateOrder(int $orderId, int $actorId): void
    {
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $orders = Table::name('work_orders');
            $templates = Table::name('workflow_template_steps');
            $steps = Table::name('order_workflow_steps');
            $templateDependencies = Table::name('workflow_template_step_dependencies');
            $dependencies = Table::name('order_step_dependencies');
            $candidates = Table::name('order_step_candidate_assignees');

            $query = $pdo->prepare("SELECT * FROM {$orders} WHERE id=? AND deleted_at IS NULL FOR UPDATE");
            $query->execute([$orderId]);
            $order = $query->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                throw new RuntimeException('سفارش پیدا نشد.');
            }
            if ((string) $order['status'] !== 'draft') {
                throw new RuntimeException('فقط سفارش پیش‌نویس قابل فعال‌سازی است.');
            }

            $query = $pdo->prepare("SELECT * FROM {$templates} WHERE workflow_template_id=? AND is_active=1 ORDER BY position,id");
            $query->execute([(int) $order['workflow_template_id']]);
            $templateSteps = $query->fetchAll(PDO::FETCH_ASSOC);
            if ($templateSteps === []) {
                throw new RuntimeException('قالب انتخاب‌شده هیچ مرحله فعالی ندارد.');
            }

            $insert = $pdo->prepare("INSERT INTO {$steps} (order_id,source_template_step_id,task_type_id,name,description,position,progress_weight) VALUES (?,?,?,?,?,?,?)");
            $stepMap = [];
            foreach ($templateSteps as $templateStep) {
                $insert->execute([$orderId, $templateStep['id'], $templateStep['task_type_id'], $templateStep['name'], $templateStep['description'], $templateStep['position'], $templateStep['progress_weight']]);
                $stepId = (int) $pdo->lastInsertId();
                $stepMap[(int) $templateStep['id']] = $stepId;
                $candidateInsert = $pdo->prepare("INSERT IGNORE INTO {$candidates} (step_id,user_id) VALUES (?,?)");
                foreach ($this->resolveTemplateAssignees((int) $templateStep['id'], $order['project_id'] !== null ? (int) $order['project_id'] : null) as $userId) {
                    $candidateInsert->execute([$stepId, $userId]);
                }
            }

            $dependencyQuery = $pdo->prepare("SELECT step_id,depends_on_step_id FROM {$templateDependencies} WHERE step_id IN (" . implode(',', array_fill(0, count($stepMap), '?')) . ')');
            $dependencyQuery->execute(array_keys($stepMap));
            $dependencyInsert = $pdo->prepare("INSERT IGNORE INTO {$dependencies} (step_id,depends_on_step_id) VALUES (?,?)");
            foreach ($dependencyQuery->fetchAll(PDO::FETCH_ASSOC) as $dependency) {
                $child = $stepMap[(int) $dependency['step_id']] ?? null;
                $parent = $stepMap[(int) $dependency['depends_on_step_id']] ?? null;
                if ($child !== null && $parent !== null) {
                    $dependencyInsert->execute([$child, $parent]);
                }
            }

            $pdo->prepare("UPDATE {$orders} SET status='active',activated_at=NOW() WHERE id=?")->execute([$orderId]);
            $this->repository->history($orderId, null, $actorId, 'order.activated', 'سفارش فعال و گردش کار ساخته شد.');
            $this->activateReadySteps($orderId, $actorId);
            $this->updateProgress($orderId, $actorId);
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function startTask(int $taskId, int $actorId, bool $manageAll): void
    {
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $task = $this->lockedTask($taskId);
            $this->assertMayWork($taskId, $actorId, $manageAll);
            if ((string) $task['status'] === 'completed') {
                throw new RuntimeException('این وظیفه قبلاً تکمیل شده است.');
            }
            if ((string) $task['status'] === 'cancelled') {
                throw new RuntimeException('این وظیفه غیرفعال شده است.');
            }
            if ((string) $task['status'] === 'open') {
                $tasks = Table::name('tasks');
                $pdo->prepare("UPDATE {$tasks} SET status='in_progress',started_by=?,started_at=NOW() WHERE id=?")->execute([$actorId, $taskId]);
                $this->repository->history($this->projectId($task), $taskId, $actorId, 'task.started', 'انجام وظیفه شروع شد.');
            }
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }

    public function addReport(int $taskId, int $actorId, string $text, bool $manageAll): int
    {
        $text = trim($text);
        if ($text === '') throw new RuntimeException('متن گزارش الزامی است.');
        $this->assertMayWork($taskId, $actorId, $manageAll);
        $task = $this->findTask($taskId);
        if (in_array((string) $task['status'], ['completed', 'cancelled'], true)) {
            throw new RuntimeException('برای این وظیفه امکان ثبت گزارش وجود ندارد.');
        }
        $reports = Table::name('task_reports');
        $statement = Connection::get()->prepare("INSERT INTO {$reports} (task_id,user_id,report_text) VALUES (?,?,?)");
        $statement->execute([$taskId, $actorId, $text]);
        $id = (int) Connection::get()->lastInsertId();
        $this->repository->history($this->projectId($task), $taskId, $actorId, 'task.reported', 'گزارش کاری ثبت شد.', ['report_id' => $id]);
        return $id;
    }

    public function completeTask(int $taskId, int $actorId, bool $manageAll, ?string $report = null): void
    {
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $task = $this->lockedTask($taskId);
            $this->assertMayWork($taskId, $actorId, $manageAll);
            if ((string) $task['status'] === 'completed') {
                $pdo->commit();
                return;
            }
            if ((string) $task['status'] === 'cancelled') {
                throw new RuntimeException('وظیفه غیرفعال است.');
            }
            if ($report !== null && trim($report) !== '') {
                $reports = Table::name('task_reports');
                $pdo->prepare("INSERT INTO {$reports} (task_id,user_id,report_text) VALUES (?,?,?)")->execute([$taskId, $actorId, trim($report)]);
                $this->repository->history($this->projectId($task), $taskId, $actorId, 'task.reported', 'گزارش نهایی وظیفه ثبت شد.');
            }
            $tasks = Table::name('tasks');
            $steps = Table::name('order_workflow_steps');
            $pdo->prepare("UPDATE {$tasks} SET status='completed',completed_by=?,completed_at=NOW() WHERE id=?")->execute([$actorId, $taskId]);
            $projectId = $this->projectId($task);
            if ($projectId !== null && $task['order_step_id'] !== null) {
                $pdo->prepare("UPDATE {$steps} SET status='completed',completed_at=NOW() WHERE id=?")->execute([(int) $task['order_step_id']]);
            }
            $this->repository->history($projectId, $taskId, $actorId, 'task.completed', 'وظیفه توسط یکی از مسئولان تکمیل شد.');
            if ($projectId !== null) {
                $this->activateReadySteps($projectId, $actorId);
                $this->updateProgress($projectId, $actorId);
            }
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }

    public function reassignTask(int $taskId, int $actorId, array $userIds): void
    {
        $userIds = $this->ids($userIds);
        if ($userIds === []) throw new RuntimeException('حداقل یک مسئول انتخاب کنید.');
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $task = $this->lockedTask($taskId);
            if (in_array((string) $task['status'], ['completed', 'cancelled'], true)) throw new RuntimeException('مسئول این وظیفه قابل تغییر نیست.');
            $this->assertEligibleAssignees($task, $userIds);
            $assignees = Table::name('task_assignees');
            $pdo->prepare("DELETE FROM {$assignees} WHERE task_id=?")->execute([$taskId]);
            $insert = $pdo->prepare("INSERT INTO {$assignees} (task_id,user_id,assigned_by) VALUES (?,?,?)");
            foreach ($userIds as $userId) {
                $insert->execute([$taskId, $userId, $actorId]);
                $this->notify($userId, 'task.assigned', 'وظیفه به شما تخصیص یافت', (string) $task['title'], '/workspace#tasks');
            }
            $this->repository->history($this->projectId($task), $taskId, $actorId, 'task.reassigned', 'مسئولان وظیفه تغییر کردند.', ['user_ids' => $userIds]);
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }

    public function addOrderStep(int $orderId, int $actorId, array $data): int
    {
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $orders = Table::name('work_orders');
            $steps = Table::name('order_workflow_steps');
            $query = $pdo->prepare("SELECT * FROM {$orders} WHERE id=? AND status='active' AND deleted_at IS NULL FOR UPDATE");
            $query->execute([$orderId]);
            $order = $query->fetch(PDO::FETCH_ASSOC);
            if (!$order) throw new RuntimeException('سفارش قابل ویرایش نیست.');

            $position = (int) $pdo->query("SELECT COALESCE(MAX(position),0)+10 FROM {$steps} WHERE order_id=" . $orderId)->fetchColumn();
            $anchorId = (int) ($data['anchor_step_id'] ?? 0);
            $placement = (string) ($data['placement'] ?? 'end');
            if ($anchorId > 0 && in_array($placement, ['before', 'after'], true)) {
                $anchorQuery = $pdo->prepare("SELECT position FROM {$steps} WHERE id=? AND order_id=?");
                $anchorQuery->execute([$anchorId, $orderId]);
                $anchorPosition = $anchorQuery->fetchColumn();
                if ($anchorPosition === false) throw new RuntimeException('مرحله مرجع معتبر نیست.');
                $position = (int) $anchorPosition + ($placement === 'after' ? 1 : 0);
                $pdo->prepare("UPDATE {$steps} SET position=position+1 WHERE order_id=? AND position>=?")->execute([$orderId, $position]);
            }
            $statement = $pdo->prepare("INSERT INTO {$steps} (order_id,task_type_id,name,description,position,progress_weight) VALUES (?,?,?,?,?,?)");
            $statement->execute([$orderId, (int) ($data['task_type_id'] ?? 0), $this->required((string) ($data['name'] ?? ''), 'نام مرحله'), $data['description'] ?? null, $position, max(.01, (float) ($data['progress_weight'] ?? 1))]);
            $stepId = (int) $pdo->lastInsertId();
            $this->replaceDependencies($orderId, $stepId, $data['dependency_ids'] ?? []);
            $candidateTable = Table::name('order_step_candidate_assignees');
            $candidateInsert = $pdo->prepare("INSERT IGNORE INTO {$candidateTable} (step_id,user_id) VALUES (?,?)");
            $candidateIds = $this->ids($data['user_ids'] ?? []);
            if ($candidateIds === [] && $order['project_id'] !== null) {
                $projectMembers = Table::name('project_members');
                $memberQuery = $pdo->prepare("SELECT user_id FROM {$projectMembers} WHERE project_id=?");
                $memberQuery->execute([(int) $order['project_id']]);
                $candidateIds = array_map('intval', $memberQuery->fetchAll(PDO::FETCH_COLUMN));
            }
            foreach ($candidateIds as $userId) $candidateInsert->execute([$stepId, $userId]);
            $this->repository->history($orderId, null, $actorId, 'step.added', 'مرحله جدید به گردش کار سفارش اضافه شد.', ['step_id' => $stepId, 'placement' => $placement, 'anchor_step_id' => $anchorId]);
            if ((string) $order['status'] === 'active') $this->activateReadySteps($orderId, $actorId);
            $this->updateProgress($orderId, $actorId);
            $pdo->commit();
            return $stepId;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }

    public function disableOrderStep(int $stepId, int $actorId): void
    {
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $steps = Table::name('order_workflow_steps');
            $tasks = Table::name('tasks');
            $query = $pdo->prepare("SELECT * FROM {$steps} WHERE id=? FOR UPDATE");
            $query->execute([$stepId]);
            $step = $query->fetch(PDO::FETCH_ASSOC);
            if (!$step) throw new RuntimeException('مرحله پیدا نشد.');
            if ((string) $step['status'] === 'completed') throw new RuntimeException('مرحله تکمیل‌شده حذف نمی‌شود؛ سابقه آن حفظ خواهد شد.');
            $pdo->prepare("UPDATE {$steps} SET status='disabled',disabled_at=NOW() WHERE id=?")->execute([$stepId]);
            $pdo->prepare("UPDATE {$tasks} SET status='cancelled' WHERE order_step_id=? AND status IN ('open','in_progress')")->execute([$stepId]);
            $orderId = (int) $step['order_id'];
            $this->repository->history($orderId, null, $actorId, 'step.disabled', 'مرحله غیرفعال شد و سابقه آن باقی ماند.', ['step_id' => $stepId]);
            $this->activateReadySteps($orderId, $actorId);
            $this->updateProgress($orderId, $actorId);
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }

    public function setStepDependencies(int $stepId, int $actorId, array $dependencyIds): void
    {
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $steps = Table::name('order_workflow_steps');
            $query = $pdo->prepare("SELECT order_id FROM {$steps} WHERE id=? FOR UPDATE");
            $query->execute([$stepId]);
            $orderId = (int) ($query->fetchColumn() ?: 0);
            if ($orderId < 1) throw new RuntimeException('مرحله پیدا نشد.');
            $this->replaceDependencies($orderId, $stepId, $dependencyIds);
            $this->assertAcyclic($orderId);
            $this->repository->history($orderId, null, $actorId, 'step.dependencies_changed', 'پیش‌نیازهای مرحله تغییر کرد.', ['step_id' => $stepId, 'dependency_ids' => $this->ids($dependencyIds)]);
            $this->activateReadySteps($orderId, $actorId);
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }

    public function reorderSteps(int $orderId, int $actorId, array $stepIds): void
    {
        $ids = $this->ids($stepIds);
        if ($ids === []) throw new RuntimeException('ترتیب مراحل خالی است.');
        $steps = Table::name('order_workflow_steps');
        $pdo = Connection::get();
        $statement = $pdo->prepare("UPDATE {$steps} SET position=? WHERE id=? AND order_id=?");
        foreach ($ids as $index => $stepId) $statement->execute([($index + 1) * 10, $stepId, $orderId]);
        $this->repository->history($orderId, null, $actorId, 'step.reordered', 'ترتیب مراحل تغییر کرد.', ['step_ids' => $ids]);
    }

    private function activateReadySteps(int $orderId, int $actorId): void
    {
        $steps = Table::name('order_workflow_steps');
        $dependencies = Table::name('order_step_dependencies');
        $sql = "SELECT s.* FROM {$steps} s WHERE s.order_id=? AND s.status='pending' AND NOT EXISTS (SELECT 1 FROM {$dependencies} d JOIN {$steps} parent ON parent.id=d.depends_on_step_id WHERE d.step_id=s.id AND parent.status NOT IN ('completed','disabled')) ORDER BY s.position,s.id";
        $query = Connection::get()->prepare($sql);
        $query->execute([$orderId]);
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $step) {
            $this->activateStep($step, $actorId);
        }
    }

    private function activateStep(array $step, int $actorId): void
    {
        $pdo = Connection::get();
        $steps = Table::name('order_workflow_steps');
        $tasks = Table::name('tasks');
        $candidates = Table::name('order_step_candidate_assignees');
        $assignees = Table::name('task_assignees');
        $pdo->prepare("UPDATE {$steps} SET status='active',activated_at=NOW() WHERE id=? AND status='pending'")->execute([(int) $step['id']]);
        $orders = Table::name('work_orders');
        $projectQuery = $pdo->prepare("SELECT title FROM {$orders} WHERE id=?");
        $projectQuery->execute([(int) $step['order_id']]);
        $projectTitle = (string) ($projectQuery->fetchColumn() ?: ('#' . (int) $step['order_id']));
        $title = (string) $step['name'] . ' — ' . $projectTitle;
        $pdo->prepare("INSERT IGNORE INTO {$tasks} (order_id,order_step_id,task_type_id,title,description) VALUES (?,?,?,?,?)")->execute([(int) $step['order_id'], (int) $step['id'], (int) $step['task_type_id'], $title, $step['description']]);
        $query = $pdo->prepare("SELECT id FROM {$tasks} WHERE order_step_id=?");
        $query->execute([(int) $step['id']]);
        $taskId = (int) $query->fetchColumn();
        $query = $pdo->prepare("SELECT user_id FROM {$candidates} WHERE step_id=?");
        $query->execute([(int) $step['id']]);
        $insert = $pdo->prepare("INSERT IGNORE INTO {$assignees} (task_id,user_id,assigned_by) VALUES (?,?,?)");
        foreach (array_map('intval', $query->fetchAll(PDO::FETCH_COLUMN)) as $userId) {
            $insert->execute([$taskId, $userId, $actorId]);
            $this->notify($userId, 'task.created', 'وظیفه جدید برای شما', $title, '/workspace#tasks');
        }
        $this->repository->history((int) $step['order_id'], $taskId, $actorId, 'step.activated', 'مرحله فعال و وظیفه آن ایجاد شد.', ['step_id' => (int) $step['id']]);
    }

    private function updateProgress(int $orderId, int $actorId): void
    {
        $steps = Table::name('order_workflow_steps');
        $orders = Table::name('work_orders');
        $statement = Connection::get()->prepare("SELECT COALESCE(SUM(CASE WHEN status='completed' THEN progress_weight ELSE 0 END),0) done_weight,COALESCE(SUM(CASE WHEN status<>'disabled' THEN progress_weight ELSE 0 END),0) total_weight,COALESCE(SUM(CASE WHEN status NOT IN ('completed','disabled') THEN 1 ELSE 0 END),0) remaining FROM {$steps} WHERE order_id=?");
        $statement->execute([$orderId]);
        $summary = $statement->fetch(PDO::FETCH_ASSOC) ?: [];
        $total = (float) ($summary['total_weight'] ?? 0);
        $progress = $total > 0 ? round(((float) ($summary['done_weight'] ?? 0) / $total) * 100, 2) : 0;
        $completed = $total > 0 && (int) ($summary['remaining'] ?? 0) === 0;
        $statement = Connection::get()->prepare("SELECT status FROM {$orders} WHERE id=?");
        $statement->execute([$orderId]);
        $oldStatus = (string) $statement->fetchColumn();
        if ($completed) {
            Connection::get()->prepare("UPDATE {$orders} SET progress_percent=100,status='completed',completed_at=COALESCE(completed_at,NOW()) WHERE id=?")->execute([$orderId]);
            if ($oldStatus !== 'completed') {
                $this->repository->history($orderId, null, $actorId, 'order.completed', 'تمام مراحل سفارش تکمیل شد.');
            }
        } else {
            Connection::get()->prepare("UPDATE {$orders} SET progress_percent=? WHERE id=?")->execute([$progress, $orderId]);
        }
    }

    private function resolveTemplateAssignees(int $templateStepId, ?int $projectId): array
    {
        $users = Table::name('users');
        $direct = Table::name('workflow_template_step_users');
        $stepTeams = Table::name('workflow_template_step_teams');
        $teamMembers = Table::name('team_members');
        $stepRoles = Table::name('workflow_template_step_roles');
        $userRoles = Table::name('user_roles');
        $sql = "SELECT DISTINCT u.id FROM {$users} u WHERE u.status='active' AND u.deleted_at IS NULL AND (EXISTS (SELECT 1 FROM {$direct} d WHERE d.step_id=? AND d.user_id=u.id) OR EXISTS (SELECT 1 FROM {$stepTeams} st JOIN {$teamMembers} tm ON tm.team_id=st.team_id WHERE st.step_id=? AND tm.user_id=u.id) OR EXISTS (SELECT 1 FROM {$stepRoles} sr JOIN {$userRoles} ur ON ur.role_id=sr.role_id WHERE sr.step_id=? AND ur.user_id=u.id))";
        $statement = Connection::get()->prepare($sql);
        $statement->execute([$templateStepId, $templateStepId, $templateStepId]);
        $defaultIds = array_values(array_unique(array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN))));
        if ($projectId === null) return $defaultIds;

        $members = Table::name('project_members');
        $statement = Connection::get()->prepare("SELECT pm.user_id FROM {$members} pm JOIN {$users} u ON u.id=pm.user_id WHERE pm.project_id=? AND u.status='active' AND u.deleted_at IS NULL");
        $statement->execute([$projectId]);
        $memberIds = array_values(array_unique(array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN))));
        if ($defaultIds === []) return $memberIds;

        $matched = array_values(array_intersect($defaultIds, $memberIds));
        return $matched !== [] ? $matched : $memberIds;
    }

    private function assertEligibleAssignees(array $task, array $userIds): void
    {
        $users = Table::name('users');
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        if ((int) ($task['order_id'] ?? 0) > 0) {
            $orders = Table::name('work_orders');
            $members = Table::name('project_members');
            $sql = "SELECT COUNT(DISTINCT u.id) FROM {$users} u JOIN {$members} pm ON pm.user_id=u.id JOIN {$orders} o ON o.project_id=pm.project_id WHERE o.id=? AND u.id IN ({$placeholders}) AND u.status='active' AND u.deleted_at IS NULL";
            $statement = Connection::get()->prepare($sql);
            $statement->execute([(int) $task['order_id'], ...$userIds]);
            if ((int) $statement->fetchColumn() !== count($userIds)) {
                throw new RuntimeException('برای تسک پروژه فقط اعضای فعال همان پروژه قابل انتخاب هستند.');
            }
            return;
        }
        $statement = Connection::get()->prepare("SELECT COUNT(*) FROM {$users} WHERE id IN ({$placeholders}) AND status='active' AND deleted_at IS NULL");
        $statement->execute($userIds);
        if ((int) $statement->fetchColumn() !== count($userIds)) {
            throw new RuntimeException('یکی از کاربران انتخاب‌شده فعال یا معتبر نیست.');
        }
    }

    private function replaceDependencies(int $orderId, int $stepId, mixed $dependencyIds): void
    {
        $ids = $this->ids($dependencyIds);
        if (in_array($stepId, $ids, true)) throw new RuntimeException('یک مرحله نمی‌تواند پیش‌نیاز خودش باشد.');
        $steps = Table::name('order_workflow_steps');
        $dependencies = Table::name('order_step_dependencies');
        $pdo = Connection::get();
        $pdo->prepare("DELETE FROM {$dependencies} WHERE step_id=?")->execute([$stepId]);
        $check = $pdo->prepare("SELECT 1 FROM {$steps} WHERE id=? AND order_id=?");
        $insert = $pdo->prepare("INSERT INTO {$dependencies} (step_id,depends_on_step_id) VALUES (?,?)");
        foreach ($ids as $parentId) {
            $check->execute([$parentId, $orderId]);
            if (!$check->fetchColumn()) throw new RuntimeException('همه پیش‌نیازها باید متعلق به همین سفارش باشند.');
            $insert->execute([$stepId, $parentId]);
        }
        $this->assertAcyclic($orderId);
    }

    private function assertAcyclic(int $orderId): void
    {
        $steps = Table::name('order_workflow_steps');
        $dependencies = Table::name('order_step_dependencies');
        $statement = Connection::get()->prepare("SELECT d.step_id,d.depends_on_step_id FROM {$dependencies} d JOIN {$steps} s ON s.id=d.step_id WHERE s.order_id=?");
        $statement->execute([$orderId]);
        $graph = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $edge) $graph[(int) $edge['step_id']][] = (int) $edge['depends_on_step_id'];
        $visiting = [];
        $visited = [];
        $walk = function (int $node) use (&$walk, &$visiting, &$visited, $graph): void {
            if (isset($visiting[$node])) throw new RuntimeException('وابستگی دوری بین مراحل مجاز نیست.');
            if (isset($visited[$node])) return;
            $visiting[$node] = true;
            foreach ($graph[$node] ?? [] as $parent) $walk($parent);
            unset($visiting[$node]);
            $visited[$node] = true;
        };
        foreach (array_keys($graph) as $node) $walk((int) $node);
    }

    private function assertMayWork(int $taskId, int $actorId, bool $manageAll): void
    {
        if ($manageAll) return;
        $assignees = Table::name('task_assignees');
        $statement = Connection::get()->prepare("SELECT 1 FROM {$assignees} WHERE task_id=? AND user_id=?");
        $statement->execute([$taskId, $actorId]);
        if (!$statement->fetchColumn()) throw new RuntimeException('این وظیفه به شما تخصیص داده نشده است.');
    }

    private function findTask(int $taskId): array
    {
        $tasks = Table::name('tasks');
        $statement = Connection::get()->prepare("SELECT * FROM {$tasks} WHERE id=?");
        $statement->execute([$taskId]);
        $task = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$task) throw new RuntimeException('وظیفه پیدا نشد.');
        return $task;
    }

    private function lockedTask(int $taskId): array
    {
        $tasks = Table::name('tasks');
        $statement = Connection::get()->prepare("SELECT * FROM {$tasks} WHERE id=? FOR UPDATE");
        $statement->execute([$taskId]);
        $task = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$task) throw new RuntimeException('وظیفه پیدا نشد.');
        return $task;
    }

    private function notify(int $userId, string $event, string $title, string $body, ?string $link): void
    {
        $table = Table::name('user_notifications');
        Connection::get()->prepare("INSERT INTO {$table} (user_id,event_type,title,body,link_url) VALUES (?,?,?,?,?)")->execute([$userId, $event, $title, $body, $link]);
    }

    private function ids(mixed $values): array
    {
        $values = is_array($values) ? $values : (is_string($values) ? explode(',', $values) : []);
        return array_values(array_unique(array_filter(array_map('intval', $values), static fn (int $id): bool => $id > 0)));
    }

    private function projectId(array $task): ?int
    {
        $id = (int) ($task['order_id'] ?? 0);
        return $id > 0 ? $id : null;
    }

    private function required(string $value, string $label): string
    {
        $value = trim($value);
        if ($value === '') throw new RuntimeException($label . ' الزامی است.');
        return $value;
    }
}
