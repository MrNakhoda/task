<?php

declare(strict_types=1);

namespace App\Pwa;

use App\Database\Connection;
use App\Support\Table;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use PDO;
use RuntimeException;
use Throwable;

final class WebPushService
{
    public function __construct(private readonly PushSubscriptionRepository $subscriptions = new PushSubscriptionRepository())
    {
    }

    public function sendNotification(int $notificationId): void
    {
        if (!PushConfig::configured()) {
            return;
        }
        if (!class_exists(WebPush::class) || !class_exists(Subscription::class)) {
            throw new RuntimeException('Web Push package is not installed. Run composer install.');
        }

        $notifications = Table::name('user_notifications');
        $users = Table::name('users');
        $statement = Connection::get()->prepare("SELECT n.* FROM {$notifications} n JOIN {$users} u ON u.id=n.user_id WHERE n.id=? AND u.status='active' AND u.deleted_at IS NULL LIMIT 1");
        $statement->execute([$notificationId]);
        $notification = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$notification) {
            return;
        }

        $targets = $this->subscriptions->pendingForNotification($notificationId, (int) $notification['user_id']);
        if ($targets === []) {
            return;
        }

        $webPush = new WebPush(['VAPID' => [
            'subject' => PushConfig::subject(),
            'publicKey' => PushConfig::publicKey(),
            'privateKey' => PushConfig::privateKey(),
        ]]);
        $payload = json_encode([
            'title' => mb_substr((string) $notification['title'], 0, 120),
            'body' => mb_substr((string) ($notification['body'] ?? ''), 0, 500),
            'url' => (string) ($notification['link_url'] ?: '/workspace#notifications'),
            'tag' => 'taskflow-' . (int) $notification['id'],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $temporaryFailures = [];

        foreach ($targets as $target) {
            try {
                $subscription = Subscription::create([
                    'endpoint' => (string) $target['endpoint'],
                    'publicKey' => (string) $target['public_key'],
                    'authToken' => (string) $target['auth_token'],
                    'contentEncoding' => (string) $target['content_encoding'],
                ]);
                $report = $webPush->sendOneNotification($subscription, $payload, [
                    'TTL' => 86400,
                    'urgency' => 'normal',
                ]);
                if ($report->isSuccess()) {
                    $this->subscriptions->delivered($notificationId, (int) $target['id']);
                    continue;
                }
                $expired = $report->isSubscriptionExpired();
                $reason = $report->getReason() ?: 'Push delivery failed.';
                $this->subscriptions->failed($notificationId, (int) $target['id'], $reason, $expired);
                if (!$expired) {
                    $temporaryFailures[] = $reason;
                }
            } catch (Throwable $exception) {
                $this->subscriptions->failed($notificationId, (int) $target['id'], $exception->getMessage(), false);
                $temporaryFailures[] = $exception->getMessage();
            }
        }

        if ($temporaryFailures !== []) {
            throw new RuntimeException('Some push deliveries failed: ' . mb_substr(implode(' | ', $temporaryFailures), 0, 800));
        }
    }
}
