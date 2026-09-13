<?php

declare(strict_types=1);

namespace App\Auth;

use App\Database\Connection;
use App\Support\Table;
use PDO;

final class AuthRepository
{
    public function findByEmail(string $email): ?array
    {
        $table = Table::name('users');
        $statement = Connection::get()->prepare("SELECT * FROM {$table} WHERE email = ? AND deleted_at IS NULL LIMIT 1");
        $statement->execute([strtolower(trim($email))]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $table = Table::name('users');
        $statement = Connection::get()->prepare("SELECT * FROM {$table} WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        $statement->execute([$id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(string $name, string $email, string $passwordHash): int
    {
        $table = Table::name('users');
        $statement = Connection::get()->prepare("INSERT INTO {$table} (name, email, password_hash, status) VALUES (?, ?, ?, 'active')");
        $statement->execute([$name, strtolower(trim($email)), $passwordHash]);
        return (int) Connection::get()->lastInsertId();
    }

    public function assignRole(int $userId, string $roleKey): void
    {
        $roles = Table::name('roles');
        $userRoles = Table::name('user_roles');
        $statement = Connection::get()->prepare("INSERT IGNORE INTO {$userRoles} (user_id, role_id) SELECT ?, id FROM {$roles} WHERE key_name = ? LIMIT 1");
        $statement->execute([$userId, $roleKey]);
    }

    public function touchLogin(int $userId): void
    {
        $table = Table::name('users');
        Connection::get()->prepare("UPDATE {$table} SET last_login_at = NOW() WHERE id = ?")->execute([$userId]);
    }

    public function updateHash(int $userId, string $hash): void
    {
        $table = Table::name('users');
        Connection::get()->prepare("UPDATE {$table} SET password_hash = ? WHERE id = ?")->execute([$hash, $userId]);
    }
}
