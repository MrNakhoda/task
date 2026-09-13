<?php

declare(strict_types=1);

namespace App\Modules\Workflow;

use App\Auth\AuthRepository;
use App\Auth\PasswordHasher;
use App\Database\Connection;
use App\Support\Table;
use PDO;
use RuntimeException;

final class WorkflowRepository
{
    public function overview(int $userId, bool $manageAll): array
    {
        $orders = Table::name('work_orders');
        $tasks = Table::name('tasks');
        $assignees = Table::name('task_assignees');
        $notifications = Table::name('user_notifications');
        $taskWhere = $manageAll ? '' : " AND EXISTS (SELECT 1 FROM {$assignees} ta WHERE ta.task_id=t.id AND ta.user_id=" . (int) $userId . ')';
        $pdo = Connection::get();

        return [
            'orders_active' => (int) $pdo->query("SELECT COUNT(*) FROM {$orders} WHERE status='active' AND deleted_at IS NULL")->fetchColumn(),
            'orders_completed' => (int) $pdo->query("SELECT COUNT(*) FROM {$orders} WHERE status='completed' AND deleted_at IS NULL")->fetchColumn(),
            'tasks_open' => (int) $pdo->query("SELECT COUNT(*) FROM {$tasks} t WHERE t.status IN ('open','in_progress'){$taskWhere}")->fetchColumn(),
            'notifications_unread' => (int) $pdo->query("SELECT COUNT(*) FROM {$notifications} WHERE user_id=" . (int) $userId . ' AND read_at IS NULL')->fetchColumn(),
        ];
    }

    public function referenceData(): array
    {
        $users = Table::name('users');
        $roles = Table::name('roles');
        $userRoles = Table::name('user_roles');
        $teams = Table::name('teams');
        $teamMembers = Table::name('team_members');
        $taskTypes = Table::name('task_types');
        $templateSteps = Table::name('workflow_template_steps');
        $orderSteps = Table::name('order_workflow_steps');
        $tasks = Table::name('tasks');
        $customers = Table::name('customers');
        $orderCustomers = Table::name('order_customers');
        $map = [
            'users' => "SELECT u.id,u.name,u.email,u.status,(SELECT GROUP_CONCAT(r.display_name ORDER BY r.id SEPARATOR '، ') FROM {$userRoles} ur JOIN {$roles} r ON r.id=ur.role_id WHERE ur.user_id=u.id) role_names,(SELECT GROUP_CONCAT(ur.role_id ORDER BY ur.role_id) FROM {$userRoles} ur WHERE ur.user_id=u.id) role_ids FROM {$users} u WHERE u.deleted_at IS NULL ORDER BY u.name",
            'roles' => "SELECT id,key_name,display_name,is_active FROM {$roles} ORDER BY id",
            'permissions' => "SELECT id,key_name,display_name FROM " . Table::name('permissions') . " ORDER BY id",
            'teams' => "SELECT t.id,t.name,t.description,t.is_active,(SELECT COUNT(*) FROM {$teamMembers} tm WHERE tm.team_id=t.id) member_count,(SELECT GROUP_CONCAT(u.name ORDER BY u.name SEPARATOR '، ') FROM {$teamMembers} tm JOIN {$users} u ON u.id=tm.user_id WHERE tm.team_id=t.id) member_names FROM {$teams} t ORDER BY t.name",
            'task_types' => "SELECT tt.id,tt.name,tt.slug,tt.color,tt.description,tt.is_active,((SELECT COUNT(*) FROM {$templateSteps} s WHERE s.task_type_id=tt.id)+(SELECT COUNT(*) FROM {$orderSteps} os WHERE os.task_type_id=tt.id)+(SELECT COUNT(*) FROM {$tasks} t WHERE t.task_type_id=tt.id)) usage_count FROM {$taskTypes} tt ORDER BY tt.name",
            'templates' => "SELECT id,name,description,version,is_active FROM " . Table::name('workflow_templates') . " WHERE deleted_at IS NULL ORDER BY name",
            'projects' => "SELECT id,name,code,status,due_at FROM " . Table::name('projects') . " ORDER BY name",
            'customers' => "SELECT c.id,c.name,c.phone,c.email,c.notes,(SELECT COUNT(*) FROM {$orderCustomers} oc WHERE oc.customer_id=c.id) order_count FROM {$customers} c WHERE c.deleted_at IS NULL ORDER BY c.name",
            'priorities' => "SELECT id,key_name,name,color,sort_order FROM " . Table::name('order_priorities') . " WHERE is_active=1 ORDER BY sort_order",
        ];
        $result = [];
        foreach ($map as $key => $sql) {
            $result[$key] = Connection::get()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        }
        return $result;
    }

    public function templates(): array
    {
        $templates = Table::name('workflow_templates');
        $steps = Table::name('workflow_template_steps');
        $types = Table::name('task_types');
        $dependencies = Table::name('workflow_template_step_dependencies');
        $stepUsers = Table::name('workflow_template_step_users');
        $stepTeams = Table::name('workflow_template_step_teams');
        $stepRoles = Table::name('workflow_template_step_roles');
        $sql = "SELECT wt.*, (SELECT COUNT(*) FROM {$steps} s WHERE s.workflow_template_id=wt.id AND s.is_active=1) step_count FROM {$templates} wt WHERE wt.deleted_at IS NULL ORDER BY wt.updated_at DESC";
        $items = Connection::get()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $stepQuery = Connection::get()->prepare("SELECT s.*,tt.name task_type_name FROM {$steps} s JOIN {$types} tt ON tt.id=s.task_type_id WHERE s.workflow_template_id=? ORDER BY s.position,s.id");
        $depQuery = Connection::get()->prepare("SELECT depends_on_step_id FROM {$dependencies} WHERE step_id=? ORDER BY depends_on_step_id");
        foreach ($items as &$template) {
            $stepQuery->execute([(int) $template['id']]);
            $template['steps'] = $stepQuery->fetchAll(PDO::FETCH_ASSOC);
            foreach ($template['steps'] as &$step) {
                $depQuery->execute([(int) $step['id']]);
                $step['dependencies'] = array_map('intval', $depQuery->fetchAll(PDO::FETCH_COLUMN));
                foreach ([
                    'user_ids' => [$stepUsers, 'user_id'],
                    'team_ids' => [$stepTeams, 'team_id'],
                    'role_ids' => [$stepRoles, 'role_id'],
                ] as $key => [$relationTable, $relationColumn]) {
                    $relation = Connection::get()->prepare("SELECT {$relationColumn} FROM {$relationTable} WHERE step_id=?");
                    $relation->execute([(int) $step['id']]);
                    $step[$key] = array_map('intval', $relation->fetchAll(PDO::FETCH_COLUMN));
                }
            }
        }
        return $items;
    }

    public function orders(array $filters = []): array
    {
        $orders = Table::name('work_orders');
        $priorities = Table::name('order_priorities');
        $projects = Table::name('projects');
        $orderCustomers = Table::name('order_customers');
        $customers = Table::name('customers');
        $where = ['o.deleted_at IS NULL'];
        $params = [];

        if (($filters['status'] ?? '') !== '') {
            $where[] = 'o.status=?';
            $params[] = (string) $filters['status'];
        }
        if ((int) ($filters['priority_id'] ?? 0) > 0) {
            $where[] = 'o.priority_id=?';
            $params[] = (int) $filters['priority_id'];
        }
        if (trim((string) ($filters['customer'] ?? '')) !== '') {
            $where[] = "EXISTS (SELECT 1 FROM {$orderCustomers} oc JOIN {$customers} c ON c.id=oc.customer_id WHERE oc.order_id=o.id AND c.name LIKE ?)";
            $params[] = '%' . trim((string) $filters['customer']) . '%';
        }
        if (($filters['weight'] ?? '') !== '' && is_numeric($filters['weight'])) {
            $where[] = "EXISTS (SELECT 1 FROM {$orderCustomers} ocw WHERE ocw.order_id=o.id AND ocw.weight=?)";
            $params[] = (float) $filters['weight'];
        }

        if (trim((string) ($filters['search'] ?? '')) !== '') {
            $where[] = '(o.title LIKE ? OR o.order_number LIKE ?)';
            $search = '%' . trim((string) $filters['search']) . '%';
            $params[] = $search;
            $params[] = $search;
        }

        $projectMembers = Table::name('project_members');
        $users = Table::name('users');
        $steps = Table::name('order_workflow_steps');
        $sql = "SELECT o.*,p.name priority_name,p.color priority_color,pr.name project_name,(SELECT GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR '، ') FROM {$orderCustomers} oc JOIN {$customers} c ON c.id=oc.customer_id WHERE oc.order_id=o.id) customer_names,(SELECT GROUP_CONCAT(u.name ORDER BY u.name SEPARATOR '، ') FROM {$projectMembers} pm JOIN {$users} u ON u.id=pm.user_id WHERE pm.project_id=o.project_id) member_names,(SELECT COUNT(*) FROM {$projectMembers} pmc WHERE pmc.project_id=o.project_id) member_count,(SELECT COUNT(*) FROM {$steps} sc WHERE sc.order_id=o.id AND sc.status<>'disabled') stage_count FROM {$orders} o LEFT JOIN {$priorities} p ON p.id=o.priority_id LEFT JOIN {$projects} pr ON pr.id=o.project_id WHERE " . implode(' AND ', $where) . ' ORDER BY p.sort_order DESC,o.created_at DESC LIMIT 300';
        $statement = Connection::get()->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function projects(array $filters = []): array
    {
        return $this->orders($filters);
    }

    public function tasks(int $userId, bool $manageAll, array $filters = []): array
    {
        $tasks = Table::name('tasks');
        $assignees = Table::name('task_assignees');
        $users = Table::name('users');
        $orders = Table::name('work_orders');
        $types = Table::name('task_types');
        $orderCustomers = Table::name('order_customers');
        $customers = Table::name('customers');
        $where = [];
        $params = [];
        if (!$manageAll) {
            $where[] = "EXISTS (SELECT 1 FROM {$assignees} mine WHERE mine.task_id=t.id AND mine.user_id=?)";
            $params[] = $userId;
        }
        if (($filters['status'] ?? '') !== '') {
            $where[] = 't.status=?';
            $params[] = (string) $filters['status'];
        }
        if ((int) ($filters['task_type_id'] ?? 0) > 0) {
            $where[] = 't.task_type_id=?';
            $params[] = (int) $filters['task_type_id'];
        }
        if ((int) ($filters['order_id'] ?? 0) > 0) {
            $where[] = 't.order_id=?';
            $params[] = (int) $filters['order_id'];
        }
        if (trim((string) ($filters['customer'] ?? '')) !== '') {
            $where[] = "EXISTS (SELECT 1 FROM {$orderCustomers} oc JOIN {$customers} c ON c.id=oc.customer_id WHERE oc.order_id=t.order_id AND c.name LIKE ?)";
            $params[] = '%' . trim((string) $filters['customer']) . '%';
        }
        $whereSql = $where === [] ? '1=1' : implode(' AND ', $where);
        $reports = Table::name('task_reports');
        $sql = "SELECT t.*,COALESCE(o.order_number,'—') order_number,COALESCE(o.title,'تسک مستقل') order_title,COALESCE(o.progress_percent,0) progress_percent,tt.name task_type_name,tt.color task_type_color,(SELECT GROUP_CONCAT(u.name ORDER BY u.name SEPARATOR '، ') FROM {$assignees} ta JOIN {$users} u ON u.id=ta.user_id WHERE ta.task_id=t.id) assignee_names,(SELECT GROUP_CONCAT(ta.user_id ORDER BY ta.user_id) FROM {$assignees} ta WHERE ta.task_id=t.id) assignee_ids,(SELECT COUNT(*) FROM {$reports} tr WHERE tr.task_id=t.id) report_count FROM {$tasks} t LEFT JOIN {$orders} o ON o.id=t.order_id JOIN {$types} tt ON tt.id=t.task_type_id WHERE {$whereSql} ORDER BY FIELD(t.status,'in_progress','open','completed'),t.created_at DESC LIMIT 300";
        $statement = Connection::get()->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createStandaloneTask(array $data, int $actorId): int
    {
        $assigneeIds = $this->ids($data['user_ids'] ?? []);
        if ($assigneeIds === []) throw new RuntimeException('حداقل یک مسئول برای تسک انتخاب کنید.');
        $this->assertActiveUsers($assigneeIds);
        $tasks = Table::name('tasks');
        $assignees = Table::name('task_assignees');
        $notifications = Table::name('user_notifications');
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $pdo->prepare("INSERT INTO {$tasks} (task_type_id,title,description,is_standalone,due_at,created_by) VALUES (?,?,?,1,?,?)")->execute([
                (int) ($data['task_type_id'] ?? 0),
                $this->required((string) ($data['title'] ?? ''), 'عنوان تسک'),
                $data['description'] ?? null,
                ($data['due_at'] ?? '') !== '' ? $data['due_at'] : null,
                $actorId,
            ]);
            $taskId = (int) $pdo->lastInsertId();
            $assign = $pdo->prepare("INSERT INTO {$assignees} (task_id,user_id,assigned_by) VALUES (?,?,?)");
            $notify = $pdo->prepare("INSERT INTO {$notifications} (user_id,event_type,title,body,link_url) VALUES (?,'task.assigned','تسک جدید برای شما',?, '/workspace#tasks')");
            foreach ($assigneeIds as $userId) {
                $assign->execute([$taskId, $userId, $actorId]);
                $notify->execute([$userId, (string) $data['title']]);
            }
            $this->history(null, $taskId, $actorId, 'task.created', 'تسک مستقل ایجاد و تخصیص داده شد.', ['user_ids' => $assigneeIds]);
            $pdo->commit();
            return $taskId;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }

    public function task(int $taskId, int $userId, bool $manageAll): ?array
    {
        $tasks = Table::name('tasks');
        $orders = Table::name('work_orders');
        $types = Table::name('task_types');
        $assignees = Table::name('task_assignees');
        $users = Table::name('users');
        $reports = Table::name('task_reports');
        $where = $manageAll ? '' : " AND EXISTS (SELECT 1 FROM {$assignees} mine WHERE mine.task_id=t.id AND mine.user_id=?)";
        $statement = Connection::get()->prepare("SELECT t.*,o.project_id,COALESCE(o.title,'تسک مستقل') order_title,tt.name task_type_name,tt.color task_type_color FROM {$tasks} t LEFT JOIN {$orders} o ON o.id=t.order_id JOIN {$types} tt ON tt.id=t.task_type_id WHERE t.id=?{$where}");
        $statement->execute($manageAll ? [$taskId] : [$taskId, $userId]);
        $task = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$task) return null;
        $statement = Connection::get()->prepare("SELECT u.id,u.name,u.email FROM {$assignees} ta JOIN {$users} u ON u.id=ta.user_id WHERE ta.task_id=? ORDER BY u.name");
        $statement->execute([$taskId]);
        $task['assignees'] = $statement->fetchAll(PDO::FETCH_ASSOC);
        $statement = Connection::get()->prepare("SELECT tr.*,u.name user_name FROM {$reports} tr JOIN {$users} u ON u.id=tr.user_id WHERE tr.task_id=? ORDER BY tr.created_at DESC,tr.id DESC");
        $statement->execute([$taskId]);
        $task['reports'] = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ((int) ($task['project_id'] ?? 0) > 0) {
            $members = Table::name('project_members');
            $statement = Connection::get()->prepare("SELECT u.id,u.name,u.email FROM {$members} pm JOIN {$users} u ON u.id=pm.user_id WHERE pm.project_id=? AND u.status='active' AND u.deleted_at IS NULL ORDER BY u.name");
            $statement->execute([(int) $task['project_id']]);
        } else {
            $statement = Connection::get()->prepare("SELECT id,name,email FROM {$users} WHERE status='active' AND deleted_at IS NULL ORDER BY name");
            $statement->execute();
        }
        $task['eligible_assignees'] = $statement->fetchAll(PDO::FETCH_ASSOC);
        return $task;
    }

    public function updateStandaloneTask(int $taskId, array $data, int $actorId): void
    {
        $tasks = Table::name('tasks');
        $statement = Connection::get()->prepare("UPDATE {$tasks} SET task_type_id=?,title=?,description=?,due_at=? WHERE id=? AND is_standalone=1 AND status NOT IN ('completed','cancelled')");
        $statement->execute([
            (int) ($data['task_type_id'] ?? 0),
            $this->required((string) ($data['title'] ?? ''), 'عنوان تسک'),
            $data['description'] ?? null,
            ($data['due_at'] ?? '') !== '' ? $data['due_at'] : null,
            $taskId,
        ]);
        if ($statement->rowCount() < 1) {
            $check = Connection::get()->prepare("SELECT 1 FROM {$tasks} WHERE id=? AND is_standalone=1 AND status NOT IN ('completed','cancelled')");
            $check->execute([$taskId]);
            if (!$check->fetchColumn()) throw new RuntimeException('تسک مستقل پیدا نشد یا دیگر قابل ویرایش نیست.');
        }
        $this->history(null, $taskId, $actorId, 'task.updated', 'مشخصات تسک مستقل ویرایش شد.');
    }

    public function deleteStandaloneTask(int $taskId): void
    {
        $tasks = Table::name('tasks');
        $statement = Connection::get()->prepare("DELETE FROM {$tasks} WHERE id=? AND is_standalone=1");
        $statement->execute([$taskId]);
        if ($statement->rowCount() < 1) throw new RuntimeException('فقط تسک مستقل را می‌توان مستقیماً حذف کرد.');
    }

    public function order(int $orderId): ?array
    {
        $orders = Table::name('work_orders');
        $priorities = Table::name('order_priorities');
        $projectTable = Table::name('projects');
        $statement = Connection::get()->prepare("SELECT o.*,p.name priority_name,p.color priority_color,pr.name project_name FROM {$orders} o LEFT JOIN {$priorities} p ON p.id=o.priority_id LEFT JOIN {$projectTable} pr ON pr.id=o.project_id WHERE o.id=? AND o.deleted_at IS NULL LIMIT 1");
        $statement->execute([$orderId]);
        $order = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            return null;
        }
        $steps = Table::name('order_workflow_steps');
        $tasks = Table::name('tasks');
        $types = Table::name('task_types');
        $history = Table::name('workflow_activity_logs');
        $users = Table::name('users');
        $orderCustomers = Table::name('order_customers');
        $customers = Table::name('customers');
        $attachments = Table::name('order_attachments');
        $files = Table::name('files');
        $reports = Table::name('task_reports');
        $dependencies = Table::name('order_step_dependencies');
        $assignees = Table::name('task_assignees');
        $q = Connection::get()->prepare("SELECT s.*,tt.name task_type_name,t.id task_id,t.status task_status,(SELECT GROUP_CONCAT(d.depends_on_step_id ORDER BY d.depends_on_step_id) FROM {$dependencies} d WHERE d.step_id=s.id) dependency_ids,(SELECT GROUP_CONCAT(u.name ORDER BY u.name SEPARATOR '، ') FROM {$assignees} ta JOIN {$users} u ON u.id=ta.user_id WHERE ta.task_id=t.id) assignee_names FROM {$steps} s JOIN {$types} tt ON tt.id=s.task_type_id LEFT JOIN {$tasks} t ON t.order_step_id=s.id WHERE s.order_id=? ORDER BY s.position,s.id");
        $q->execute([$orderId]);
        $order['steps'] = $q->fetchAll(PDO::FETCH_ASSOC);
        $q = Connection::get()->prepare("SELECT h.*,u.name actor_name FROM {$history} h LEFT JOIN {$users} u ON u.id=h.actor_id WHERE h.order_id=? ORDER BY h.created_at DESC,h.id DESC LIMIT 300");
        $q->execute([$orderId]);
        $order['history'] = $q->fetchAll(PDO::FETCH_ASSOC);
        $q = Connection::get()->prepare("SELECT c.*,oc.weight,oc.weight_unit,oc.notes order_customer_notes FROM {$orderCustomers} oc JOIN {$customers} c ON c.id=oc.customer_id WHERE oc.order_id=? ORDER BY c.name");
        $q->execute([$orderId]);
        $order['customers'] = $q->fetchAll(PDO::FETCH_ASSOC);
        $q = Connection::get()->prepare("SELECT a.id,a.caption,a.created_at,f.path,f.original_name,f.mime_type,f.size_bytes FROM {$attachments} a JOIN {$files} f ON f.id=a.file_id WHERE a.order_id=? AND f.deleted_at IS NULL ORDER BY a.id DESC");
        $q->execute([$orderId]);
        $order['attachments'] = $q->fetchAll(PDO::FETCH_ASSOC);
        $q = Connection::get()->prepare("SELECT r.*,u.name user_name,t.title task_title FROM {$reports} r JOIN {$tasks} t ON t.id=r.task_id JOIN {$users} u ON u.id=r.user_id WHERE t.order_id=? ORDER BY r.created_at DESC,r.id DESC");
        $q->execute([$orderId]);
        $order['reports'] = $q->fetchAll(PDO::FETCH_ASSOC);
        $projectMembers = Table::name('project_members');
        $q = Connection::get()->prepare("SELECT u.id,u.name,u.email,pm.role_label FROM {$projectMembers} pm JOIN {$users} u ON u.id=pm.user_id WHERE pm.project_id=? ORDER BY u.name");
        $q->execute([(int) ($order['project_id'] ?? 0)]);
        $order['members'] = $q->fetchAll(PDO::FETCH_ASSOC);
        return $order;
    }

    public function attachOrderImage(int $orderId, int $actorId, string $path, string $originalName, string $mime, int $size, ?string $caption): int
    {
        $orders = Table::name('work_orders');
        $files = Table::name('files');
        $attachments = Table::name('order_attachments');
        $pdo = Connection::get();
        $check = $pdo->prepare("SELECT 1 FROM {$orders} WHERE id=? AND deleted_at IS NULL");
        $check->execute([$orderId]);
        if (!$check->fetchColumn()) throw new RuntimeException('سفارش پیدا نشد.');
        $pdo->beginTransaction();
        try {
            $pdo->prepare("INSERT INTO {$files} (owner_id,path,original_name,mime_type,size_bytes) VALUES (?,?,?,?,?)")->execute([$actorId, $path, $originalName, $mime, max(0, $size)]);
            $fileId = (int) $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO {$attachments} (order_id,file_id,uploaded_by,caption) VALUES (?,?,?,?)")->execute([$orderId, $fileId, $actorId, $caption]);
            $attachmentId = (int) $pdo->lastInsertId();
            $this->history($orderId, null, $actorId, 'order.attachment_added', 'تصویر به سفارش پیوست شد.', ['attachment_id' => $attachmentId]);
            $pdo->commit();
            return $attachmentId;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }

    public function notifications(int $userId): array
    {
        $table = Table::name('user_notifications');
        $statement = Connection::get()->prepare("SELECT * FROM {$table} WHERE user_id=? ORDER BY created_at DESC,id DESC LIMIT 100");
        $statement->execute([$userId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markNotificationsRead(int $userId): void
    {
        $table = Table::name('user_notifications');
        Connection::get()->prepare("UPDATE {$table} SET read_at=COALESCE(read_at,NOW()) WHERE user_id=?")->execute([$userId]);
    }

    public function createUser(string $name, string $email, string $password, string $roleKey): int
    {
        $name = trim($name);
        $email = strtolower(trim($email));
        if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
            throw new RuntimeException('نام، ایمیل یا رمز عبور معتبر نیست.');
        }
        $users = new AuthRepository();
        if ($users->findByEmail($email) !== null) {
            throw new RuntimeException('این ایمیل قبلاً ثبت شده است.');
        }
        $id = $users->create($name, $email, (new PasswordHasher())->hash($password));
        $users->assignRole($id, $roleKey !== '' ? $roleKey : 'user');
        return $id;
    }

    public function createRole(string $key, string $name, array $permissions): int
    {
        $key = strtolower(trim($key));
        if (preg_match('/^[a-z][a-z0-9_.-]{1,59}$/', $key) !== 1 || trim($name) === '') {
            throw new RuntimeException('مشخصات نقش معتبر نیست.');
        }
        $roles = Table::name('roles');
        $rolePermissions = Table::name('role_permissions');
        $permissionTable = Table::name('permissions');
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $pdo->prepare("INSERT INTO {$roles} (key_name,display_name) VALUES (?,?)")->execute([$key, trim($name)]);
            $id = (int) $pdo->lastInsertId();
            $assign = $pdo->prepare("INSERT IGNORE INTO {$rolePermissions} (role_id,permission_id) SELECT ?,id FROM {$permissionTable} WHERE key_name=?");
            foreach ($this->ids($permissions, false) as $permission) {
                $assign->execute([$id, (string) $permission]);
            }
            $pdo->commit();
            return $id;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }

    public function assignRole(int $userId, int $roleId): void
    {
        $table = Table::name('user_roles');
        Connection::get()->prepare("INSERT IGNORE INTO {$table} (user_id,role_id) VALUES (?,?)")->execute([$userId, $roleId]);
    }

    public function replaceUserRole(int $userId, int $roleId, int $actorId): void
    {
        $roles = Table::name('roles');
        $userRoles = Table::name('user_roles');
        $rolePermissions = Table::name('role_permissions');
        $permissions = Table::name('permissions');
        $pdo = Connection::get();
        $check = $pdo->prepare("SELECT 1 FROM {$roles} WHERE id=? AND is_active=1");
        $check->execute([$roleId]);
        if (!$check->fetchColumn()) throw new RuntimeException('نقش انتخاب‌شده معتبر نیست.');
        if ($userId === $actorId) {
            $check = $pdo->prepare("SELECT 1 FROM {$rolePermissions} rp JOIN {$permissions} p ON p.id=rp.permission_id WHERE rp.role_id=? AND p.key_name='system.admin'");
            $check->execute([$roleId]);
            if (!$check->fetchColumn()) throw new RuntimeException('ادمین نمی‌تواند نقش مدیریتی حساب فعلی خودش را حذف کند.');
        }
        $pdo->beginTransaction();
        try {
            $pdo->prepare("DELETE FROM {$userRoles} WHERE user_id=?")->execute([$userId]);
            $pdo->prepare("INSERT INTO {$userRoles} (user_id,role_id) VALUES (?,?)")->execute([$userId, $roleId]);
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }

    public function createTeam(string $name, ?string $description, int $actorId): int
    {
        $table = Table::name('teams');
        $statement = Connection::get()->prepare("INSERT INTO {$table} (name,description,created_by) VALUES (?,?,?)");
        $statement->execute([$this->required($name, 'نام گروه'), $description, $actorId]);
        return (int) Connection::get()->lastInsertId();
    }

    public function addTeamMember(int $teamId, int $userId, bool $lead): void
    {
        $table = Table::name('team_members');
        Connection::get()->prepare("INSERT INTO {$table} (team_id,user_id,is_lead) VALUES (?,?,?) ON DUPLICATE KEY UPDATE is_lead=VALUES(is_lead)")->execute([$teamId, $userId, $lead ? 1 : 0]);
    }

    public function createTaskType(string $name, string $slug, string $color, ?string $description): int
    {
        $slug = strtolower(trim($slug));
        if (preg_match('/^[a-z][a-z0-9_-]{1,99}$/', $slug) !== 1) throw new RuntimeException('کلید نوع وظیفه معتبر نیست.');
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $color) !== 1) $color = '#3157d5';
        $table = Table::name('task_types');
        $statement = Connection::get()->prepare("INSERT INTO {$table} (name,slug,color,description) VALUES (?,?,?,?)");
        $statement->execute([$this->required($name, 'نام نوع وظیفه'), $slug, $color, $description]);
        return (int) Connection::get()->lastInsertId();
    }

    public function updateTaskType(int $taskTypeId, array $data): void
    {
        $slug = strtolower(trim((string) ($data['slug'] ?? '')));
        if (preg_match('/^[a-z][a-z0-9_-]{1,99}$/', $slug) !== 1) throw new RuntimeException('کلید انگلیسی نوع وظیفه معتبر نیست.');
        $color = (string) ($data['color'] ?? '#3157d5');
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $color) !== 1) $color = '#3157d5';
        $table = Table::name('task_types');
        $statement = Connection::get()->prepare("UPDATE {$table} SET name=?,slug=?,color=?,description=?,is_active=? WHERE id=?");
        $statement->execute([$this->required((string) ($data['name'] ?? ''), 'نام نوع وظیفه'), $slug, $color, $data['description'] ?? null, (bool) ($data['is_active'] ?? true) ? 1 : 0, $taskTypeId]);
        if ($statement->rowCount() < 1) {
            $check = Connection::get()->prepare("SELECT 1 FROM {$table} WHERE id=?");
            $check->execute([$taskTypeId]);
            if (!$check->fetchColumn()) throw new RuntimeException('نوع وظیفه پیدا نشد.');
        }
    }

    public function deleteTaskType(int $taskTypeId): void
    {
        $types = Table::name('task_types');
        $templateSteps = Table::name('workflow_template_steps');
        $orderSteps = Table::name('order_workflow_steps');
        $tasks = Table::name('tasks');
        $statement = Connection::get()->prepare("SELECT ((SELECT COUNT(*) FROM {$templateSteps} WHERE task_type_id=?)+(SELECT COUNT(*) FROM {$orderSteps} WHERE task_type_id=?)+(SELECT COUNT(*) FROM {$tasks} WHERE task_type_id=?))");
        $statement->execute([$taskTypeId, $taskTypeId, $taskTypeId]);
        if ((int) $statement->fetchColumn() > 0) throw new RuntimeException('این نوع وظیفه استفاده شده است؛ ابتدا موارد وابسته را حذف یا نوع آن‌ها را تغییر دهید.');
        $statement = Connection::get()->prepare("DELETE FROM {$types} WHERE id=?");
        $statement->execute([$taskTypeId]);
        if ($statement->rowCount() < 1) throw new RuntimeException('نوع وظیفه پیدا نشد.');
    }

    public function createProject(string $name, ?string $code, ?string $description, int $actorId): int
    {
        $table = Table::name('projects');
        $statement = Connection::get()->prepare("INSERT INTO {$table} (name,code,description,created_by) VALUES (?,?,?,?)");
        $statement->execute([$this->required($name, 'نام پروژه'), $code !== '' ? $code : null, $description, $actorId]);
        return (int) Connection::get()->lastInsertId();
    }

    public function createWorkflowProject(array $data, int $actorId): int
    {
        $projects = Table::name('projects');
        $members = Table::name('project_members');
        $orders = Table::name('work_orders');
        $orderCustomers = Table::name('order_customers');
        $name = $this->required((string) ($data['name'] ?? $data['title'] ?? ''), 'نام پروژه');
        $code = trim((string) ($data['code'] ?? ''));
        if ($code === '') $code = 'PRJ-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $pdo->prepare("INSERT INTO {$projects} (name,code,description,status,due_at,created_by) VALUES (?,?,?,'active',?,?)")->execute([$name, $code, $data['description'] ?? null, ($data['due_at'] ?? '') !== '' ? substr((string) $data['due_at'], 0, 10) : null, $actorId]);
            $containerId = (int) $pdo->lastInsertId();

            $memberIds = $this->ids($data['member_ids'] ?? []);
            $memberIds[] = $actorId;
            $memberIds = array_values(array_unique($memberIds));
            $this->assertActiveUsers($memberIds);
            $addMember = $pdo->prepare("INSERT IGNORE INTO {$members} (project_id,user_id,role_label) VALUES (?,?,?)");
            foreach ($memberIds as $userId) $addMember->execute([$containerId, $userId, null]);

            $pdo->prepare("INSERT INTO {$orders} (order_number,title,project_id,workflow_template_id,priority_id,details_json,due_at,created_by) VALUES (?,?,?,?,?,?,?,?)")->execute([
                $code,
                $name,
                $containerId,
                (int) ($data['workflow_template_id'] ?? 0),
                $this->nullableId($data['priority_id'] ?? null),
                json_encode(['description' => (string) ($data['description'] ?? '')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                ($data['due_at'] ?? '') !== '' ? $data['due_at'] : null,
                $actorId,
            ]);
            $projectId = (int) $pdo->lastInsertId();
            if ((int) ($data['customer_id'] ?? 0) > 0) {
                $pdo->prepare("INSERT INTO {$orderCustomers} (order_id,customer_id,weight,weight_unit) VALUES (?,?,?,'gram')")->execute([$projectId, (int) $data['customer_id'], ($data['weight'] ?? '') !== '' ? (float) $data['weight'] : null]);
            }
            $this->history($projectId, null, $actorId, 'project.created', 'پروژه ایجاد شد.', ['code' => $code, 'member_ids' => $memberIds]);
            $pdo->commit();
            return $projectId;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }

    public function updateWorkflowProject(int $workflowProjectId, array $data, int $actorId): void
    {
        $orders = Table::name('work_orders');
        $projects = Table::name('projects');
        $orderCustomers = Table::name('order_customers');
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $query = $pdo->prepare("SELECT project_id,order_number FROM {$orders} WHERE id=? AND deleted_at IS NULL FOR UPDATE");
            $query->execute([$workflowProjectId]);
            $current = $query->fetch(PDO::FETCH_ASSOC);
            if (!$current || (int) ($current['project_id'] ?? 0) < 1) throw new RuntimeException('پروژه پیدا نشد.');
            $name = $this->required((string) ($data['name'] ?? ''), 'نام پروژه');
            $code = trim((string) ($data['code'] ?? $current['order_number']));
            if ($code === '') $code = (string) $current['order_number'];
            $dueAt = ($data['due_at'] ?? '') !== '' ? $data['due_at'] : null;
            $description = (string) ($data['description'] ?? '');
            $pdo->prepare("UPDATE {$projects} SET name=?,code=?,description=?,due_at=? WHERE id=?")->execute([$name, $code, $description !== '' ? $description : null, $dueAt !== null ? substr((string) $dueAt, 0, 10) : null, (int) $current['project_id']]);
            $pdo->prepare("UPDATE {$orders} SET order_number=?,title=?,priority_id=?,details_json=?,due_at=? WHERE id=?")->execute([$code, $name, $this->nullableId($data['priority_id'] ?? null), json_encode(['description' => $description], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), $dueAt, $workflowProjectId]);
            $pdo->prepare("DELETE FROM {$orderCustomers} WHERE order_id=?")->execute([$workflowProjectId]);
            if ((int) ($data['customer_id'] ?? 0) > 0) {
                $pdo->prepare("INSERT INTO {$orderCustomers} (order_id,customer_id,weight,weight_unit) VALUES (?,?,?,'gram')")->execute([$workflowProjectId, (int) $data['customer_id'], ($data['weight'] ?? '') !== '' ? (float) $data['weight'] : null]);
            }
            $this->history($workflowProjectId, null, $actorId, 'project.updated', 'مشخصات پروژه ویرایش شد.');
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }

    public function addProjectMember(int $projectId, int $userId, ?string $label): void
    {
        $this->assertActiveUsers([$userId]);
        $table = Table::name('project_members');
        Connection::get()->prepare("INSERT INTO {$table} (project_id,user_id,role_label) VALUES (?,?,?) ON DUPLICATE KEY UPDATE role_label=VALUES(role_label)")->execute([$projectId, $userId, $label]);
    }

    public function addWorkflowProjectMember(int $workflowProjectId, int $userId, ?string $label, int $actorId): void
    {
        $orders = Table::name('work_orders');
        $query = Connection::get()->prepare("SELECT project_id FROM {$orders} WHERE id=? AND deleted_at IS NULL");
        $query->execute([$workflowProjectId]);
        $containerId = (int) ($query->fetchColumn() ?: 0);
        if ($containerId < 1) throw new RuntimeException('پروژه پیدا نشد.');
        $this->addProjectMember($containerId, $userId, $label);
        $this->history($workflowProjectId, null, $actorId, 'project.member_added', 'عضو جدید به پروژه اضافه شد.', ['user_id' => $userId]);
    }

    public function removeWorkflowProjectMember(int $workflowProjectId, int $userId, int $actorId): void
    {
        $orders = Table::name('work_orders');
        $members = Table::name('project_members');
        $tasks = Table::name('tasks');
        $assignees = Table::name('task_assignees');
        $query = Connection::get()->prepare("SELECT project_id FROM {$orders} WHERE id=? AND deleted_at IS NULL");
        $query->execute([$workflowProjectId]);
        $containerId = (int) ($query->fetchColumn() ?: 0);
        if ($containerId < 1) throw new RuntimeException('پروژه پیدا نشد.');
        $query = Connection::get()->prepare("SELECT COUNT(*) FROM {$tasks} t JOIN {$assignees} ta ON ta.task_id=t.id WHERE t.order_id=? AND ta.user_id=? AND t.status IN ('open','in_progress')");
        $query->execute([$workflowProjectId, $userId]);
        if ((int) $query->fetchColumn() > 0) throw new RuntimeException('ابتدا تسک‌های باز این عضو را به فرد دیگری تخصیص دهید.');
        $statement = Connection::get()->prepare("DELETE FROM {$members} WHERE project_id=? AND user_id=?");
        $statement->execute([$containerId, $userId]);
        if ($statement->rowCount() < 1) throw new RuntimeException('این کاربر عضو پروژه نیست.');
        $this->history($workflowProjectId, null, $actorId, 'project.member_removed', 'عضو از پروژه حذف شد.', ['user_id' => $userId]);
    }

    public function deleteWorkflowProject(int $workflowProjectId): void
    {
        $orders = Table::name('work_orders');
        $projects = Table::name('projects');
        $attachments = Table::name('order_attachments');
        $files = Table::name('files');
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $query = $pdo->prepare("SELECT project_id FROM {$orders} WHERE id=? AND deleted_at IS NULL FOR UPDATE");
            $query->execute([$workflowProjectId]);
            $containerId = (int) ($query->fetchColumn() ?: 0);
            if ($containerId < 1) throw new RuntimeException('پروژه پیدا نشد.');

            $query = $pdo->prepare("SELECT file_id FROM {$attachments} WHERE order_id=?");
            $query->execute([$workflowProjectId]);
            $fileIds = array_map('intval', $query->fetchAll(PDO::FETCH_COLUMN));

            $pdo->prepare("DELETE FROM {$orders} WHERE id=?")->execute([$workflowProjectId]);
            $pdo->prepare("DELETE FROM {$projects} WHERE id=?")->execute([$containerId]);
            if ($fileIds !== []) {
                $placeholders = implode(',', array_fill(0, count($fileIds), '?'));
                $pdo->prepare("DELETE FROM {$files} WHERE id IN ({$placeholders})")->execute($fileIds);
            }
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }

    public function deleteWorkflowTemplate(int $templateId): void
    {
        $templates = Table::name('workflow_templates');
        $orders = Table::name('work_orders');
        $pdo = Connection::get();
        $query = $pdo->prepare("SELECT name FROM {$templates} WHERE id=? AND deleted_at IS NULL");
        $query->execute([$templateId]);
        if ($query->fetchColumn() === false) throw new RuntimeException('قالب گردش‌کار پیدا نشد.');
        $query = $pdo->prepare("SELECT COUNT(*) FROM {$orders} WHERE workflow_template_id=?");
        $query->execute([$templateId]);
        if ((int) $query->fetchColumn() > 0) {
            throw new RuntimeException('این قالب در پروژه‌ها استفاده شده است؛ ابتدا پروژه‌های مرتبط را حذف کنید.');
        }
        $pdo->prepare("DELETE FROM {$templates} WHERE id=?")->execute([$templateId]);
    }

    public function wipeTestData(): void
    {
        $tables = [
            'notifications' => Table::name('user_notifications'),
            'history' => Table::name('workflow_activity_logs'),
            'tasks' => Table::name('tasks'),
            'orders' => Table::name('work_orders'),
            'projects' => Table::name('projects'),
            'templates' => Table::name('workflow_templates'),
            'teams' => Table::name('teams'),
            'customers' => Table::name('customers'),
            'attachments' => Table::name('order_attachments'),
            'files' => Table::name('files'),
        ];
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $fileIds = array_map('intval', $pdo->query("SELECT file_id FROM {$tables['attachments']}")->fetchAll(PDO::FETCH_COLUMN));
            $pdo->exec("DELETE FROM {$tables['notifications']}");
            $pdo->exec("DELETE FROM {$tables['history']}");
            $pdo->exec("DELETE FROM {$tables['tasks']}");
            $pdo->exec("DELETE FROM {$tables['orders']}");
            $pdo->exec("DELETE FROM {$tables['projects']}");
            $pdo->exec("DELETE FROM {$tables['templates']}");
            $pdo->exec("DELETE FROM {$tables['teams']}");
            $pdo->exec("DELETE FROM {$tables['customers']}");
            if ($fileIds !== []) {
                $placeholders = implode(',', array_fill(0, count($fileIds), '?'));
                $pdo->prepare("DELETE FROM {$tables['files']} WHERE id IN ({$placeholders})")->execute($fileIds);
            }
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }

    public function createCustomer(string $name, ?string $phone, ?string $email, ?string $notes): int
    {
        if ($email !== null && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('ایمیل مشتری معتبر نیست.');
        $table = Table::name('customers');
        $statement = Connection::get()->prepare("INSERT INTO {$table} (name,phone,email,notes) VALUES (?,?,?,?)");
        $statement->execute([$this->required($name, 'نام مشتری'), $phone, $email ?: null, $notes]);
        return (int) Connection::get()->lastInsertId();
    }

    public function updateCustomer(int $customerId, array $data): void
    {
        $email = trim((string) ($data['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('ایمیل مشتری معتبر نیست.');
        $table = Table::name('customers');
        $statement = Connection::get()->prepare("UPDATE {$table} SET name=?,phone=?,email=?,notes=? WHERE id=? AND deleted_at IS NULL");
        $statement->execute([$this->required((string) ($data['name'] ?? ''), 'نام مشتری'), $data['phone'] ?? null, $email !== '' ? $email : null, $data['notes'] ?? null, $customerId]);
        if ($statement->rowCount() < 1) {
            $check = Connection::get()->prepare("SELECT 1 FROM {$table} WHERE id=? AND deleted_at IS NULL");
            $check->execute([$customerId]);
            if (!$check->fetchColumn()) throw new RuntimeException('مشتری پیدا نشد.');
        }
    }

    public function deleteCustomer(int $customerId): void
    {
        $customers = Table::name('customers');
        $orderCustomers = Table::name('order_customers');
        $query = Connection::get()->prepare("SELECT COUNT(*) FROM {$orderCustomers} WHERE customer_id=?");
        $query->execute([$customerId]);
        if ((int) $query->fetchColumn() > 0) throw new RuntimeException('این مشتری در پروژه‌ها استفاده شده است و فعلاً قابل حذف نیست.');
        $statement = Connection::get()->prepare("UPDATE {$customers} SET deleted_at=NOW() WHERE id=? AND deleted_at IS NULL");
        $statement->execute([$customerId]);
        if ($statement->rowCount() < 1) throw new RuntimeException('مشتری پیدا نشد.');
    }

    public function createTemplate(string $name, ?string $description, int $actorId): int
    {
        $table = Table::name('workflow_templates');
        $statement = Connection::get()->prepare("INSERT INTO {$table} (name,description,created_by) VALUES (?,?,?)");
        $statement->execute([$this->required($name, 'نام قالب'), $description, $actorId]);
        return (int) Connection::get()->lastInsertId();
    }

    public function updateTemplate(int $templateId, array $data): void
    {
        $table = Table::name('workflow_templates');
        $statement = Connection::get()->prepare("UPDATE {$table} SET name=?,description=?,is_active=?,version=version+1 WHERE id=? AND deleted_at IS NULL");
        $statement->execute([$this->required((string) ($data['name'] ?? ''), 'نام قالب'), $data['description'] ?? null, (bool) ($data['is_active'] ?? true) ? 1 : 0, $templateId]);
        if ($statement->rowCount() < 1) {
            $check = Connection::get()->prepare("SELECT 1 FROM {$table} WHERE id=? AND deleted_at IS NULL");
            $check->execute([$templateId]);
            if (!$check->fetchColumn()) throw new RuntimeException('قالب پیدا نشد.');
        }
    }

    public function addTemplateStep(int $templateId, array $data): int
    {
        $steps = Table::name('workflow_template_steps');
        $deps = Table::name('workflow_template_step_dependencies');
        $users = Table::name('workflow_template_step_users');
        $teams = Table::name('workflow_template_step_teams');
        $roles = Table::name('workflow_template_step_roles');
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $position = max(0, (int) ($data['position'] ?? 0));
            $weight = max(0.01, (float) ($data['progress_weight'] ?? 1));
            $statement = $pdo->prepare("INSERT INTO {$steps} (workflow_template_id,task_type_id,name,description,position,progress_weight) VALUES (?,?,?,?,?,?)");
            $statement->execute([$templateId, (int) ($data['task_type_id'] ?? 0), $this->required((string) ($data['name'] ?? ''), 'نام مرحله'), $data['description'] ?? null, $position, $weight]);
            $stepId = (int) $pdo->lastInsertId();
            $this->insertPairs($deps, 'step_id', $stepId, 'depends_on_step_id', $this->ids($data['dependency_ids'] ?? []));
            $this->insertPairs($users, 'step_id', $stepId, 'user_id', $this->ids($data['user_ids'] ?? []));
            $this->insertPairs($teams, 'step_id', $stepId, 'team_id', $this->ids($data['team_ids'] ?? []));
            $this->insertPairs($roles, 'step_id', $stepId, 'role_id', $this->ids($data['role_ids'] ?? []));
            $pdo->prepare("UPDATE " . Table::name('workflow_templates') . " SET version=version+1 WHERE id=?")->execute([$templateId]);
            $pdo->commit();
            return $stepId;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }

    public function updateTemplateStep(int $stepId, array $data): void
    {
        $steps = Table::name('workflow_template_steps');
        $deps = Table::name('workflow_template_step_dependencies');
        $users = Table::name('workflow_template_step_users');
        $teams = Table::name('workflow_template_step_teams');
        $roles = Table::name('workflow_template_step_roles');
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $query = $pdo->prepare("SELECT workflow_template_id FROM {$steps} WHERE id=? FOR UPDATE");
            $query->execute([$stepId]);
            $templateId = (int) ($query->fetchColumn() ?: 0);
            if ($templateId < 1) throw new RuntimeException('مرحله قالب پیدا نشد.');
            $dependencyIds = $this->ids($data['dependency_ids'] ?? []);
            if (in_array($stepId, $dependencyIds, true)) throw new RuntimeException('مرحله نمی‌تواند پیش‌نیاز خودش باشد.');
            $check = $pdo->prepare("SELECT 1 FROM {$steps} WHERE id=? AND workflow_template_id=?");
            foreach ($dependencyIds as $parentId) {
                $check->execute([$parentId, $templateId]);
                if (!$check->fetchColumn()) throw new RuntimeException('پیش‌نیاز باید متعلق به همین قالب باشد.');
            }
            $pdo->prepare("UPDATE {$steps} SET task_type_id=?,name=?,description=?,position=?,progress_weight=?,is_active=? WHERE id=?")->execute([(int) ($data['task_type_id'] ?? 0), $this->required((string) ($data['name'] ?? ''), 'نام مرحله'), $data['description'] ?? null, max(0, (int) ($data['position'] ?? 0)), max(.01, (float) ($data['progress_weight'] ?? 1)), (bool) ($data['is_active'] ?? true) ? 1 : 0, $stepId]);
            foreach ([$deps, $users, $teams, $roles] as $table) $pdo->prepare("DELETE FROM {$table} WHERE step_id=?")->execute([$stepId]);
            $this->insertPairs($deps, 'step_id', $stepId, 'depends_on_step_id', $dependencyIds);
            $this->insertPairs($users, 'step_id', $stepId, 'user_id', $this->ids($data['user_ids'] ?? []));
            $this->insertPairs($teams, 'step_id', $stepId, 'team_id', $this->ids($data['team_ids'] ?? []));
            $this->insertPairs($roles, 'step_id', $stepId, 'role_id', $this->ids($data['role_ids'] ?? []));
            $pdo->prepare("UPDATE " . Table::name('workflow_templates') . " SET version=version+1 WHERE id=?")->execute([$templateId]);
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }

    public function disableTemplateStep(int $stepId): void
    {
        $steps = Table::name('workflow_template_steps');
        $templates = Table::name('workflow_templates');
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $query = $pdo->prepare("SELECT workflow_template_id FROM {$steps} WHERE id=? FOR UPDATE");
            $query->execute([$stepId]);
            $templateId = (int) ($query->fetchColumn() ?: 0);
            if ($templateId < 1) throw new RuntimeException('مرحله قالب پیدا نشد.');
            $pdo->prepare("UPDATE {$steps} SET is_active=0 WHERE id=?")->execute([$stepId]);
            $pdo->prepare("UPDATE {$templates} SET version=version+1 WHERE id=?")->execute([$templateId]);
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }

    public function createPriority(string $key, string $name, string $color, int $sortOrder): int
    {
        $key = strtolower(trim($key));
        if (preg_match('/^[a-z][a-z0-9_-]{1,59}$/', $key) !== 1) throw new RuntimeException('کلید اولویت معتبر نیست.');
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $color) !== 1) $color = '#64748b';
        $table = Table::name('order_priorities');
        $statement = Connection::get()->prepare("INSERT INTO {$table} (key_name,name,color,sort_order) VALUES (?,?,?,?)");
        $statement->execute([$key, $this->required($name, 'نام اولویت'), $color, $sortOrder]);
        return (int) Connection::get()->lastInsertId();
    }

    public function createOrder(array $data, int $actorId): int
    {
        $orders = Table::name('work_orders');
        $orderCustomers = Table::name('order_customers');
        $number = trim((string) ($data['order_number'] ?? ''));
        if ($number === '') $number = 'ORD-' . date('Ymd-His') . '-' . random_int(100, 999);
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare("INSERT INTO {$orders} (order_number,title,project_id,workflow_template_id,priority_id,details_json,due_at,created_by) VALUES (?,?,?,?,?,?,?,?)");
            $statement->execute([
                $number,
                $this->required((string) ($data['title'] ?? ''), 'عنوان سفارش'),
                $this->nullableId($data['project_id'] ?? null),
                (int) ($data['workflow_template_id'] ?? 0),
                $this->nullableId($data['priority_id'] ?? null),
                isset($data['details']) ? json_encode($data['details'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : null,
                ($data['due_at'] ?? '') !== '' ? $data['due_at'] : null,
                $actorId,
            ]);
            $orderId = (int) $pdo->lastInsertId();
            $attach = $pdo->prepare("INSERT INTO {$orderCustomers} (order_id,customer_id,weight,weight_unit,notes) VALUES (?,?,?,?,?)");
            foreach ((array) ($data['customers'] ?? []) as $customer) {
                if (!is_array($customer) || (int) ($customer['customer_id'] ?? 0) < 1) continue;
                $attach->execute([$orderId, (int) $customer['customer_id'], ($customer['weight'] ?? '') !== '' ? (float) $customer['weight'] : null, (string) ($customer['weight_unit'] ?? 'gram'), $customer['notes'] ?? null]);
            }
            $this->history($orderId, null, $actorId, 'order.created', 'سفارش ایجاد شد.', ['order_number' => $number]);
            $pdo->commit();
            return $orderId;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }

    public function history(?int $orderId, ?int $taskId, ?int $actorId, string $event, string $message, array $metadata = []): void
    {
        $table = Table::name('workflow_activity_logs');
        $statement = Connection::get()->prepare("INSERT INTO {$table} (order_id,task_id,actor_id,event_type,message,metadata_json) VALUES (?,?,?,?,?,?)");
        $statement->execute([$orderId, $taskId, $actorId, $event, $message, $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
    }

    private function insertPairs(string $table, string $leftColumn, int $leftId, string $rightColumn, array $rightIds): void
    {
        if (preg_match('/^[a-z_]+$/', $leftColumn . $rightColumn) !== 1) throw new RuntimeException('Invalid relation column.');
        $statement = Connection::get()->prepare("INSERT IGNORE INTO {$table} ({$leftColumn},{$rightColumn}) VALUES (?,?)");
        foreach ($rightIds as $rightId) $statement->execute([$leftId, $rightId]);
    }

    private function ids(mixed $values, bool $integers = true): array
    {
        $values = is_array($values) ? $values : (is_string($values) ? explode(',', $values) : []);
        if (!$integers) return array_values(array_unique(array_filter(array_map('strval', $values))));
        return array_values(array_unique(array_filter(array_map('intval', $values), static fn (int $id): bool => $id > 0)));
    }

    private function nullableId(mixed $value): ?int
    {
        $id = (int) $value;
        return $id > 0 ? $id : null;
    }

    private function assertActiveUsers(array $userIds): void
    {
        $userIds = $this->ids($userIds);
        if ($userIds === []) throw new RuntimeException('حداقل یک کاربر فعال انتخاب کنید.');
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $users = Table::name('users');
        $statement = Connection::get()->prepare("SELECT COUNT(*) FROM {$users} WHERE id IN ({$placeholders}) AND status='active' AND deleted_at IS NULL");
        $statement->execute($userIds);
        if ((int) $statement->fetchColumn() !== count($userIds)) {
            throw new RuntimeException('یکی از کاربران انتخاب‌شده فعال یا معتبر نیست.');
        }
    }

    private function required(string $value, string $label): string
    {
        $value = trim($value);
        if ($value === '') throw new RuntimeException($label . ' الزامی است.');
        return $value;
    }
}
