<?php

declare(strict_types=1);

namespace App\Audit;

use App\Database\Connection;
use App\Support\Env;
use App\Support\Table;

final class AuditLogger
{
    public function record(?int $actorId, string $action, ?string $entityType = null, ?string $entityId = null, array $metadata = []): void
    {
        $table = Table::name('audit_logs');
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli');
        $ipHash = hash_hmac('sha256', $ip, Env::get('APP_KEY', 'development-only-key') ?? 'development-only-key');
        $statement = Connection::get()->prepare("INSERT INTO {$table} (actor_id, action, entity_type, entity_id, metadata_json, ip_hash) VALUES (?, ?, ?, ?, ?, ?)");
        $statement->execute([
            $actorId,
            substr($action, 0, 120),
            $entityType !== null ? substr($entityType, 0, 80) : null,
            $entityId !== null ? substr($entityId, 0, 100) : null,
            $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $ipHash,
        ]);
    }
}
