<?php

declare(strict_types=1);

namespace ARM\Auth;

final class CsrfTokenManager
{
    private const SESSION_KEY = 'arm_csrf_tokens';
    private int $ttl;

    public function __construct(int $ttl = 3600)
    {
        $this->ttl = $ttl;
    }

    public function issue(string $action = 'default'): string
    {
        $this->ensureSession();

        $token = bin2hex(random_bytes(32));
        $_SESSION[self::SESSION_KEY][$action] = [
            'token' => $token,
            'expires' => time() + $this->ttl,
        ];

        return $token;
    }

    public function verify(?string $token, string $action = 'default'): bool
    {
        $this->ensureSession();

        if ($token === null || $token === '') {
            return false;
        }

        $stored = $_SESSION[self::SESSION_KEY][$action] ?? null;
        if (!$stored) {
            return false;
        }

        if (($stored['expires'] ?? 0) < time()) {
            unset($_SESSION[self::SESSION_KEY][$action]);
            return false;
        }

        return hash_equals((string) $stored['token'], (string) $token);
    }

    private function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
                'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            ]);
        }
    }
}
