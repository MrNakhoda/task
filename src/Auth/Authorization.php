<?php

declare(strict_types=1);

namespace App\Auth;

use App\Database\Connection;
use App\Support\Table;

final class Authorization
{
    public function allows(int $userId, string $permission): bool
    {
        $roles = Table::name('roles');
        $permissions = Table::name('permissions');
        $userRoles = Table::name('user_roles');
        $rolePermissions = Table::name('role_permissions');

        $sql = "SELECT 1 FROM {$userRoles} ur INNER JOIN {$roles} r ON r.id = ur.role_id INNER JOIN {$rolePermissions} rp ON rp.role_id = r.id INNER JOIN {$permissions} p ON p.id = rp.permission_id WHERE ur.user_id = ? AND r.is_active = 1 AND p.key_name = ? LIMIT 1";
        $statement = Connection::get()->prepare($sql);
        $statement->execute([$userId, $permission]);
        return (bool) $statement->fetchColumn();
    }
}
