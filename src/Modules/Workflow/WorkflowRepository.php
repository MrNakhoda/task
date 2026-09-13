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
        $map = [
            'users' => "SELECT id,name,email,status FROM " . Table::name('users') . " WHERE deleted_at IS NULL ORDER BY name",
            'roles' => "SELECT id,key_name,display_name,is_active FROM " . Table::name('roles') . " ORDER BY id",
            'teams' => "SELECT id,name,description,is_active FROM " . Table::name('teams') . " ORDER BY name",
            'task_types' => "SELECT id,name,slug,color,is_active FROM " . Table::name('task_types') . " ORDER BY name",
            'templates' => "SELECT id,name,description,version,is_active FROM " . Table::name('workflow_templates') . " WHERE deleted_at IS NULL ORDER BY name",
            'projects' => "SELECT id,name,code,status,due_at FROM " . Table::name('projects') . " ORDER BY name",
            'customers' => "SELECT id,name,phone,email FROM " . Table::name('customers') . " WHERE deleted_at IS NULL ORDER BY name",
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

        $sql = "SELECT o.*,p.name priority_name,p.color priority_color,pr.name project_name,(SELECT GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR '، ') FROM {$orderCustomers} oc JOIN {$customers} c ON c.id=oc.customer_id WHERE oc.order_id=o.id) customer_names FROM {$orders} o LEFT JOIN {$priorities} p ON p.id=o.priority_id LEFT JOIN {$projects} pr ON pr.id=o.project_id WHERE " . implode(' AND ', $where) . ' ORDER BY p.sort_order DESC,o.created_at DESC LIMIT 300';
        $statement = Connection::get()->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
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
        $sql = "SELECT t.*,o.order_number,o.title order_title,o.progress_percent,tt.name task_type_name,tt.color task_type_color,(SELECT GROUP_CONCAT(u.name ORDER BY u.name SEPARATOR '، ') FROM {$assignees} ta JOIN {$users} u ON u.id=ta.user_id WHERE ta.task_id=t.id) assignee_names FROM {$tasks} t JOIN {$orders} o ON o.id=t.order_id JOIN {$types} tt ON tt.id=t.task_type_id WHERE {$whereSql} ORDER BY FIELD(t.status,'in_progress','open','completed'),t.created_at DESC LIMIT 300";
        $statement = Connection::get()->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function order(int $orderId): ?array
    {
        $orders = Table::name('work_orders');
        $statement = Connection::get()->prepare("SELECT * FROM {$orders} WHERE id=? AND deleted_at IS NULL LIMIT 1");
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
        $q = Connection::get()->prepare("SELECT s.*,tt.name task_type_name,t.id task_id,t.status task_status FROM {$steps} s JOIN {$types} tt ON tt.id=s.task_type_id LEFT JOIN {$tasks} t ON t.order_step_id=s.id WHERE s.order_id=? ORDER BY s.position,s.id");
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

    public function createProject(string $name, ?string $code, ?string $description, int $actorId): int
    {
        $table = Table::name('projects');
        $statement = Connection::get()->prepare("INSERT INTO {$table} (name,code,description,created_by) VALUES (?,?,?,?)");
        $statement->execute([$this->required($name, 'نام پروژه'), $code !== '' ? $code : null, $description, $actorId]);
        return (int) Connection::get()->lastInsertId();
    }

    public function addProjectMember(int $projectId, int $userId, ?string $label): void
    {
        $table = Table::name('project_members');
        Connection::get()->prepare("INSERT INTO {$table} (project_id,user_id,role_label) VALUES (?,?,?) ON DUPLICATE KEY UPDATE role_label=VALUES(role_label)")->execute([$projectId, $userId, $label]);
    }

    public function createCustomer(string $name, ?string $phone, ?string $email, ?string $notes): int
    {
        if ($email !== null && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('ایمیل مشتری معتبر نیست.');
        $table = Table::name('customers');
        $statement = Connection::get()->prepare("INSERT INTO {$table} (name,phone,email,notes) VALUES (?,?,?,?)");
        $statement->execute([$this->required($name, 'نام مشتری'), $phone, $email ?: null, $notes]);
        return (int) Connection::get()->lastInsertId();
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
        if ($statement->rowCount() < 1) throw new RuntimeException('قالب پیدا نشد یا تغییری نداشت.');
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

    private function required(string $value, string $label): string
    {
        $value = trim($value);
        if ($value === '') throw new RuntimeException($label . ' الزامی است.');
        return $value;
    }
}
