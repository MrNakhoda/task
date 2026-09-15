<?php

declare(strict_types=1);

namespace App\Auth;

use App\Database\Connection;
use App\Support\Env;
use App\Support\Table;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class RememberLoginService
{
    private const DEFAULT_COOKIE = 'taskflow_remember';
    private const MAX_TOKENS_PER_USER = 8;

    public function issue(int $userId, int $authVersion): void
    {
        $selector = bin2hex(random_bytes(16));
        $validator = $this->base64Url(random_bytes(32));
        $expiresAt = new DateTimeImmutable('+' . $this->lifetimeDays() . ' days');
        $table = Table::name('remember_login_tokens');
        $pdo = Connection::get();

        $pdo->prepare("DELETE FROM {$table} WHERE expires_at <= NOW()")
            ->execute();
        $statement = $pdo->prepare("INSERT INTO {$table} (user_id, selector, validator_hash, auth_version, expires_at) VALUES (?, ?, ?, ?, ?)");
        $statement->execute([
            $userId,
            $selector,
            hash('sha256', $validator),
            max(1, $authVersion),
            $expiresAt->format('Y-m-d H:i:s'),
        ]);
        $pdo->prepare("DELETE FROM {$table} WHERE user_id=? AND id NOT IN (SELECT id FROM (SELECT id FROM {$table} WHERE user_id=? ORDER BY created_at DESC,id DESC LIMIT " . self::MAX_TOKENS_PER_USER . ') kept)')
            ->execute([$userId, $userId]);

        $this->writeCookie($selector . '.' . $validator, $expiresAt->getTimestamp());
    }

    /** @return array<string, mixed>|null */
    public function consume(): ?array
    {
        $parts = $this->cookieParts();
        if ($parts === null) {
            return null;
        }
        [$selector, $validator] = $parts;
        $tokens = Table::name('remember_login_tokens');
        $users = Table::name('users');
        $statement = Connection::get()->prepare("SELECT t.id token_id,t.validator_hash,t.auth_version token_auth_version,t.expires_at,u.* FROM {$tokens} t JOIN {$users} u ON u.id=t.user_id WHERE t.selector=? AND t.expires_at>NOW() AND u.deleted_at IS NULL LIMIT 1");
        $statement->execute([$selector]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$row || !hash_equals((string) $row['validator_hash'], hash('sha256', $validator))) {
            $this->revokeSelector($selector);
            $this->clearCookie();
            return null;
        }
        if (($row['status'] ?? '') !== 'active' || (int) ($row['auth_version'] ?? 1) !== (int) $row['token_auth_version']) {
            $this->revokeSelector($selector);
            $this->clearCookie();
            return null;
        }

        $newValidator = $this->base64Url(random_bytes(32));
        $update = Connection::get()->prepare("UPDATE {$tokens} SET validator_hash=?,last_used_at=NOW() WHERE id=? AND validator_hash=?");
        $update->execute([hash('sha256', $newValidator), (int) $row['token_id'], (string) $row['validator_hash']]);
        if ($update->rowCount() !== 1) {
            $this->revokeSelector($selector);
            $this->clearCookie();
            return null;
        }

        $expiry = new DateTimeImmutable((string) $row['expires_at'], new DateTimeZone(date_default_timezone_get()));
        $this->writeCookie($selector . '.' . $newValidator, $expiry->getTimestamp());
        unset($row['token_id'], $row['validator_hash'], $row['token_auth_version'], $row['expires_at']);
        return $row;
    }

    public function revokeCurrent(): void
    {
        $parts = $this->cookieParts();
        if ($parts !== null) {
            $this->revokeSelector($parts[0]);
        }
        $this->clearCookie();
    }

    public function revokeAll(int $userId): void
    {
        $table = Table::name('remember_login_tokens');
        Connection::get()->prepare("DELETE FROM {$table} WHERE user_id=?")->execute([$userId]);
    }

    private function revokeSelector(string $selector): void
    {
        $table = Table::name('remember_login_tokens');
        Connection::get()->prepare("DELETE FROM {$table} WHERE selector=?")->execute([$selector]);
    }

    /** @return array{string,string}|null */
    private function cookieParts(): ?array
    {
        $value = (string) ($_COOKIE[$this->cookieName()] ?? '');
        if (preg_match('/^([a-f0-9]{32})\.([A-Za-z0-9_-]{43})$/D', $value, $match) !== 1) {
            return null;
        }
        return [$match[1], $match[2]];
    }

    private function writeCookie(string $value, int $expires): void
    {
        if (!headers_sent()) {
            setcookie($this->cookieName(), $value, $this->cookieOptions($expires));
        }
        $_COOKIE[$this->cookieName()] = $value;
    }

    private function clearCookie(): void
    {
        if (!headers_sent()) {
            setcookie($this->cookieName(), '', $this->cookieOptions(time() - 3600));
        }
        unset($_COOKIE[$this->cookieName()]);
    }

    /** @return array{expires:int,path:string,secure:bool,httponly:bool,samesite:string} */
    private function cookieOptions(int $expires): array
    {
        $appUrl = Env::get('APP_URL', '') ?? '';
        $path = (string) (parse_url($appUrl, PHP_URL_PATH) ?: '/');
        $path = '/' . trim($path, '/');
        if ($path !== '/') {
            $path .= '/';
        }
        $secure = str_starts_with(strtolower($appUrl), 'https://')
            || (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off');
        return ['expires' => $expires, 'path' => $path, 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax'];
    }

    private function lifetimeDays(): int
    {
        return max(1, min(90, (int) (Env::get('AUTH_REMEMBER_DAYS', '30') ?? '30')));
    }

    private function cookieName(): string
    {
        $name = Env::get('AUTH_REMEMBER_COOKIE', self::DEFAULT_COOKIE) ?? self::DEFAULT_COOKIE;
        return preg_match('/^[A-Za-z0-9_-]{1,60}$/D', $name) === 1 ? $name : self::DEFAULT_COOKIE;
    }

    private function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
