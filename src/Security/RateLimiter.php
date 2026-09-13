<?php

declare(strict_types=1);

namespace App\Security;

use App\Database\Connection;
use App\Support\Env;
use App\Support\Table;
use RuntimeException;

final class RateLimiter
{
    public static function hit(string $action, int $maxHits, int $windowSeconds, ?string $subject = null): void
    {
        $action = preg_replace('/[^a-zA-Z0-9_.-]/', '', $action) ?: 'generic';
        $maxHits = max(1, $maxHits);
        $windowSeconds = max(1, $windowSeconds);
        $subject ??= (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $secret = Env::get('APP_KEY', '') ?? '';
        $subjectHash = hash_hmac('sha256', $subject, $secret !== '' ? $secret : 'development-only-key');
        $table = Table::name('rate_limits');
        $pdo = Connection::get();
        $ownsTransaction = !$pdo->inTransaction();

        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $select = $pdo->prepare("SELECT hits, TIMESTAMPDIFF(SECOND, window_started_at, NOW()) AS elapsed FROM {$table} WHERE action_key = ? AND subject_hash = ? FOR UPDATE");
            $select->execute([$action, $subjectHash]);
            $row = $select->fetch();

            if (!$row) {
                $pdo->prepare("INSERT INTO {$table} (action_key, subject_hash, hits, window_started_at) VALUES (?, ?, 1, NOW())")
                    ->execute([$action, $subjectHash]);
            } elseif ((int) $row['elapsed'] >= $windowSeconds) {
                $pdo->prepare("UPDATE {$table} SET hits = 1, window_started_at = NOW() WHERE action_key = ? AND subject_hash = ?")
                    ->execute([$action, $subjectHash]);
            } elseif ((int) $row['hits'] >= $maxHits) {
                throw new RuntimeException('Too many requests. Try again later.');
            } else {
                $pdo->prepare("UPDATE {$table} SET hits = hits + 1 WHERE action_key = ? AND subject_hash = ?")
                    ->execute([$action, $subjectHash]);
            }

            if ($ownsTransaction) {
                $pdo->commit();
            }
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }
}
