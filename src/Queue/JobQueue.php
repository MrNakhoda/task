<?php

declare(strict_types=1);

namespace App\Queue;

use App\Database\Connection;
use App\Support\Table;
use PDO;
use Throwable;

final class JobQueue
{
    public function push(string $type, array $payload, ?string $uniqueKey = null, ?\DateTimeInterface $availableAt = null): int
    {
        $table = Table::name('jobs');
        $statement = Connection::get()->prepare("INSERT INTO {$table} (job_type, payload_json, unique_key, status, available_at) VALUES (?, ?, ?, 'pending', ?)");
        $statement->execute([
            $type,
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $uniqueKey,
            ($availableAt ?? new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
        return (int) Connection::get()->lastInsertId();
    }

    public function workOnce(callable $handler): bool
    {
        $table = Table::name('jobs');
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $job = $pdo->query("SELECT * FROM {$table} WHERE status = 'pending' AND available_at <= NOW() ORDER BY id ASC LIMIT 1 FOR UPDATE")->fetch(PDO::FETCH_ASSOC);
            if (!$job) {
                $pdo->commit();
                return false;
            }
            $pdo->prepare("UPDATE {$table} SET status = 'processing', attempts = attempts + 1, reserved_at = NOW() WHERE id = ?")
                ->execute([(int) $job['id']]);
            $pdo->commit();

            $payload = json_decode((string) $job['payload_json'], true);
            $handler((string) $job['job_type'], is_array($payload) ? $payload : []);
            $pdo->prepare("UPDATE {$table} SET status = 'completed', completed_at = NOW(), last_error = NULL WHERE id = ?")
                ->execute([(int) $job['id']]);
            return true;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (isset($job['id'])) {
                $delay = min(3600, 30 * (2 ** min(6, (int) ($job['attempts'] ?? 0))));
                $statement = $pdo->prepare("UPDATE {$table} SET status = IF(attempts >= max_attempts, 'failed', 'pending'), available_at = DATE_ADD(NOW(), INTERVAL ? SECOND), last_error = ? WHERE id = ?");
                $statement->execute([$delay, substr($exception->getMessage(), 0, 1000), (int) $job['id']]);
            }
            throw $exception;
        }
    }
}
