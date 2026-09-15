<?php

declare(strict_types=1);

namespace App\Notification;

use App\Database\Connection;
use App\Pwa\PushConfig;
use App\Queue\JobQueue;
use App\Support\Table;

final class NotificationService
{
    public function __construct(private readonly JobQueue $queue = new JobQueue())
    {
    }

    public function email(string $recipient, string $subject, string $body, ?string $uniqueKey = null): int
    {
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Notification email is invalid.');
        }
        return $this->queue->push('notification.email', [
            'recipient' => strtolower($recipient),
            'subject' => $subject,
            'body' => $body,
        ], $uniqueKey);
    }

    public function user(int $userId, string $event, string $title, string $body = '', ?string $link = null, ?string $dedupeKey = null): ?int
    {
        $table = Table::name('user_notifications');
        $statement = Connection::get()->prepare("INSERT IGNORE INTO {$table} (user_id,event_type,title,body,link_url,dedupe_key) VALUES (?,?,?,?,?,?)");
        $statement->execute([
            $userId,
            mb_substr(trim($event), 0, 100),
            mb_substr(trim($title), 0, 190),
            mb_substr(trim($body), 0, 1000),
            $link !== null ? mb_substr($link, 0, 500) : null,
            $dedupeKey !== null ? mb_substr($dedupeKey, 0, 190) : null,
        ]);
        if ($statement->rowCount() !== 1) {
            return null;
        }
        $notificationId = (int) Connection::get()->lastInsertId();
        if (PushConfig::configured()) {
            $this->queue->push('notification.web_push', ['notification_id' => $notificationId], 'web-push-notification-' . $notificationId);
        }
        return $notificationId;
    }
}
