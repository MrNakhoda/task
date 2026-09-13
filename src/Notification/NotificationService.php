<?php

declare(strict_types=1);

namespace App\Notification;

use App\Queue\JobQueue;

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
}
