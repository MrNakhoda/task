<?php

declare(strict_types=1);

namespace App\Modules\Workflow;

use App\Database\Connection;
use App\Notification\NotificationService;
use App\Support\Table;
use DateTimeImmutable;
use PDO;

final class DueReminderService
{
    public function __construct(private readonly NotificationService $notifications = new NotificationService())
    {
    }

    public function dispatch(): int
    {
        $tasks = Table::name('tasks');
        $assignees = Table::name('task_assignees');
        $users = Table::name('users');
        $orders = Table::name('work_orders');
        $sql = "SELECT t.id,t.title,t.due_at,ta.user_id FROM {$tasks} t JOIN {$assignees} ta ON ta.task_id=t.id JOIN {$users} u ON u.id=ta.user_id LEFT JOIN {$orders} o ON o.id=t.order_id WHERE t.status IN ('open','in_progress') AND t.archived_at IS NULL AND t.due_at IS NOT NULL AND t.due_at<DATE_ADD(NOW(),INTERVAL 1 DAY) AND u.status='active' AND u.deleted_at IS NULL AND (t.order_id IS NULL OR (o.archived_at IS NULL AND o.deleted_at IS NULL)) ORDER BY t.due_at,t.id";
        $rows = Connection::get()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $now = new DateTimeImmutable();
        $today = $now->format('Y-m-d');
        $created = 0;
        foreach ($rows as $row) {
            $due = new DateTimeImmutable((string) $row['due_at']);
            $overdue = $due < $now;
            $event = $overdue ? 'task.overdue' : 'task.due_today';
            $title = $overdue ? 'مهلت یک وظیفه گذشته است' : 'موعد یک وظیفه امروز است';
            $suffix = $overdue ? $due->format('Y-m-d') : $today;
            $id = $this->notifications->user(
                (int) $row['user_id'],
                $event,
                $title,
                (string) $row['title'],
                '/workspace?task=' . (int) $row['id'] . '#tasks',
                $event . ':' . (int) $row['id'] . ':' . $suffix,
            );
            if ($id !== null) {
                $created++;
            }
        }
        return $created;
    }
}
