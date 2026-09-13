<?php

declare(strict_types=1);

namespace App\Auth;

use App\Database\Connection;
use App\Security\RateLimiter;
use App\Support\Env;
use RuntimeException;

final class AuthService
{
    private const DUMMY_HASH = '$2y$10$yPCzsPFxR2d.5o/sfXWSLuu16r0cLrN4dqiZLQtH8hWqrCJ.Mme.G';

    private bool $resolved = false;
    private ?array $current = null;

    public function __construct(
        private readonly AuthRepository $users = new AuthRepository(),
        private readonly PasswordHasher $hasher = new PasswordHasher(),
    ) {
    }

    public function register(string $name, string $email, string $password): array
    {
        if (!Env::bool('AUTH_ALLOW_REGISTRATION', false)) {
            throw new RuntimeException('Registration is disabled.');
        }
        RateLimiter::hit('auth.register', 10, 3600);

        $name = trim($name);
        $email = strtolower(trim($email));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            throw new RuntimeException('Name must be between 2 and 120 characters.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Email address is invalid.');
        }
        if (strlen($password) < 10) {
            throw new RuntimeException('Password must contain at least 10 characters.');
        }
        if ($this->users->findByEmail($email) !== null) {
            throw new RuntimeException('An account already exists for this email.');
        }

        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $id = $this->users->create($name, $email, $this->hasher->hash($password));
            $this->users->assignRole($id, 'user');
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        $this->establish($id, 1);
        return $this->publicUser($this->users->findById($id) ?? []);
    }

    public function attempt(string $email, string $password): ?array
    {
        RateLimiter::hit('auth.login', 20, 600);
        $user = $this->users->findByEmail(strtolower(trim($email)));
        $hash = is_array($user) ? (string) ($user['password_hash'] ?? '') : self::DUMMY_HASH;
        $valid = $this->hasher->verify($password, $hash);
        if (!$valid || !is_array($user) || ($user['status'] ?? '') !== 'active') {
            return null;
        }

        $id = (int) $user['id'];
        if ($this->hasher->needsRehash($hash)) {
            $this->users->updateHash($id, $this->hasher->hash($password));
        }
        $this->establish($id, (int) ($user['auth_version'] ?? 1));
        $this->users->touchLogin($id);
        return $this->publicUser($user);
    }

    public function user(): ?array
    {
        if ($this->resolved) {
            return $this->current;
        }
        $this->resolved = true;

        $key = Env::get('AUTH_SESSION_KEY', 'app_user_id') ?? 'app_user_id';
        $id = $_SESSION[$key] ?? null;
        if (!is_int($id) && !ctype_digit((string) $id)) {
            return null;
        }

        $user = $this->users->findById((int) $id);
        $sessionVersion = (int) ($_SESSION['app_auth_version'] ?? 0);
        if ($user === null || ($user['status'] ?? '') !== 'active' || (int) ($user['auth_version'] ?? 1) !== $sessionVersion) {
            unset($_SESSION[$key], $_SESSION['app_auth_version']);
            return null;
        }

        return $this->current = $this->publicUser($user);
    }

    public function logout(): void
    {
        $key = Env::get('AUTH_SESSION_KEY', 'app_user_id') ?? 'app_user_id';
        unset($_SESSION[$key], $_SESSION['app_auth_version']);
        $this->resolved = true;
        $this->current = null;
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    private function establish(int $userId, int $authVersion): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('A PHP session is required for authentication.');
        }
        session_regenerate_id(true);
        $key = Env::get('AUTH_SESSION_KEY', 'app_user_id') ?? 'app_user_id';
        $_SESSION[$key] = $userId;
        $_SESSION['app_auth_version'] = max(1, $authVersion);
        $this->resolved = false;
        $this->current = null;
    }

    private function publicUser(array $user): array
    {
        return [
            'id' => (int) ($user['id'] ?? 0),
            'name' => (string) ($user['name'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'status' => (string) ($user['status'] ?? ''),
            'email_verified_at' => $user['email_verified_at'] ?? null,
        ];
    }
}
