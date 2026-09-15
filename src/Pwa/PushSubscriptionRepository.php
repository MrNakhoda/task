<?php

declare(strict_types=1);

namespace App\Pwa;

use App\Database\Connection;
use App\Support\Table;
use PDO;
use RuntimeException;

final class PushSubscriptionRepository
{
    public function save(int $userId, array $data, ?string $userAgent = null): int
    {
        [$endpoint, $publicKey, $authToken, $encoding] = $this->validated($data);
        $table = Table::name('push_subscriptions');
        $hash = hash('sha256', $endpoint);
        $statement = Connection::get()->prepare("INSERT INTO {$table} (user_id,endpoint,endpoint_hash,public_key,auth_token,content_encoding,user_agent,last_used_at,revoked_at) VALUES (?,?,?,?,?,?,?,NOW(),NULL) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id),endpoint=VALUES(endpoint),public_key=VALUES(public_key),auth_token=VALUES(auth_token),content_encoding=VALUES(content_encoding),user_agent=VALUES(user_agent),last_used_at=NOW(),revoked_at=NULL");
        $statement->execute([$userId, $endpoint, $hash, $publicKey, $authToken, $encoding, $userAgent !== null ? mb_substr($userAgent, 0, 500) : null]);
        $query = Connection::get()->prepare("SELECT id FROM {$table} WHERE endpoint_hash=? LIMIT 1");
        $query->execute([$hash]);
        return (int) $query->fetchColumn();
    }

    public function revoke(int $userId, string $endpoint): void
    {
        if ($endpoint === '' || strlen($endpoint) > 2048) {
            return;
        }
        $table = Table::name('push_subscriptions');
        Connection::get()->prepare("UPDATE {$table} SET revoked_at=COALESCE(revoked_at,NOW()) WHERE user_id=? AND endpoint_hash=?")
            ->execute([$userId, hash('sha256', $endpoint)]);
    }

    public function revokeAll(int $userId): void
    {
        $table = Table::name('push_subscriptions');
        Connection::get()->prepare("UPDATE {$table} SET revoked_at=COALESCE(revoked_at,NOW()) WHERE user_id=?")
            ->execute([$userId]);
    }

    /** @return list<array<string, mixed>> */
    public function pendingForNotification(int $notificationId, int $userId): array
    {
        $subscriptions = Table::name('push_subscriptions');
        $deliveries = Table::name('push_notification_deliveries');
        $statement = Connection::get()->prepare("SELECT s.* FROM {$subscriptions} s LEFT JOIN {$deliveries} d ON d.subscription_id=s.id AND d.notification_id=? WHERE s.user_id=? AND s.revoked_at IS NULL AND (d.delivered_at IS NULL AND COALESCE(d.attempts,0)<5) ORDER BY s.id");
        $statement->execute([$notificationId, $userId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function delivered(int $notificationId, int $subscriptionId): void
    {
        $table = Table::name('push_notification_deliveries');
        Connection::get()->prepare("INSERT INTO {$table} (notification_id,subscription_id,attempts,delivered_at,last_attempt_at) VALUES (?,?,1,NOW(),NOW()) ON DUPLICATE KEY UPDATE attempts=attempts+1,delivered_at=NOW(),last_attempt_at=NOW(),last_error=NULL")
            ->execute([$notificationId, $subscriptionId]);
    }

    public function failed(int $notificationId, int $subscriptionId, string $reason, bool $expired): void
    {
        $deliveries = Table::name('push_notification_deliveries');
        $subscriptions = Table::name('push_subscriptions');
        Connection::get()->prepare("INSERT INTO {$deliveries} (notification_id,subscription_id,attempts,last_attempt_at,last_error) VALUES (?,?,1,NOW(),?) ON DUPLICATE KEY UPDATE attempts=attempts+1,last_attempt_at=NOW(),last_error=VALUES(last_error)")
            ->execute([$notificationId, $subscriptionId, mb_substr($reason, 0, 1000)]);
        if ($expired) {
            Connection::get()->prepare("UPDATE {$subscriptions} SET revoked_at=COALESCE(revoked_at,NOW()) WHERE id=?")
                ->execute([$subscriptionId]);
        }
    }

    /** @return array{string,string,string,string} */
    private function validated(array $data): array
    {
        $endpoint = trim((string) ($data['endpoint'] ?? ''));
        $keys = is_array($data['keys'] ?? null) ? $data['keys'] : [];
        $publicKey = trim((string) ($keys['p256dh'] ?? ''));
        $authToken = trim((string) ($keys['auth'] ?? ''));
        $encoding = trim((string) ($data['contentEncoding'] ?? 'aes128gcm'));
        $host = strtolower((string) parse_url($endpoint, PHP_URL_HOST));
        $scheme = strtolower((string) parse_url($endpoint, PHP_URL_SCHEME));
        $local = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        if ($endpoint === '' || strlen($endpoint) > 2048 || ($scheme !== 'https' && !$local)) {
            throw new RuntimeException('نشانی اشتراک اعلان معتبر نیست.');
        }
        if (preg_match('/^[A-Za-z0-9_-]{60,190}$/D', $publicKey) !== 1 || preg_match('/^[A-Za-z0-9_-]{16,190}$/D', $authToken) !== 1) {
            throw new RuntimeException('کلید اشتراک اعلان معتبر نیست.');
        }
        if (!in_array($encoding, ['aes128gcm', 'aesgcm'], true)) {
            $encoding = 'aes128gcm';
        }
        return [$endpoint, $publicKey, $authToken, $encoding];
    }
}
