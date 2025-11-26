<?php

declare(strict_types=1);

namespace ARM\Auth;

use ARM\Database\Config;
use ARM\Database\ConnectionFactory;
use DateInterval;
use DateTimeImmutable;
use PDO;

final class AuthService
{
    private PDO $pdo;
    private string $prefix;
    private CsrfTokenManager $csrf;
    private JwtService $jwt;
    private Authorization $authorization;

    private function __construct(PDO $pdo, string $prefix, string $secret)
    {
        $this->pdo = $pdo;
        $this->prefix = $prefix;
        $this->csrf = new CsrfTokenManager();
        $this->jwt = new JwtService($secret);
        $this->authorization = new Authorization($pdo, $prefix);

        $this->primeCurrentUser();
    }

    public static function fromEnvironment(): self
    {
        $config = self::configFromEnvironment();
        $pdo = ConnectionFactory::make($config);
        $secret = $_ENV['APP_KEY'] ?? ($_ENV['JWT_SECRET'] ?? 'arm-repair-estimates');

        return new self($pdo, $config->getPrefix(), $secret);
    }

    public function login(string $email, string $password): ?array
    {
        $stmt = $this->pdo->prepare(sprintf(
            'SELECT u.*, r.slug AS role_slug FROM `%sarm_users` u JOIN `%sarm_roles` r ON r.id = u.role_id WHERE u.email = :email LIMIT 1',
            $this->prefix,
            $this->prefix
        ));
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            return null;
        }

        if (($user['status'] ?? '') === 'disabled') {
            return null;
        }

        $this->startSession();
        $_SESSION['arm_user_id'] = (int) $user['id'];
        $_SESSION['arm_user_role'] = $user['role_slug'];

        $jwt = $this->jwt->encode([
            'sub' => (int) $user['id'],
            'role' => $user['role_slug'],
        ]);

        $csrf = $this->csrf->issue('default');
        $this->authorization->setCurrentUser($user);
        $this->sendSessionCookie($jwt);

        return [
            'token' => $jwt,
            'csrf_token' => $csrf,
            'user' => $this->publicUser($user),
        ];
    }

    public function logout(): void
    {
        $this->startSession();
        unset($_SESSION['arm_user_id'], $_SESSION['arm_user_role']);
        $this->clearSessionCookie();
        session_regenerate_id(true);
    }

    public function currentUser(): ?array
    {
        return $this->authorization->user();
    }

    public function can(string $capability): bool
    {
        return $this->authorization->can($capability);
    }

    public function csrf(): CsrfTokenManager
    {
        return $this->csrf;
    }

    public function inviteUser(string $email, string $name, int $roleId, ?int $invitedBy = null): array
    {
        $token = bin2hex(random_bytes(24));
        $stmt = $this->pdo->prepare(sprintf(
            'INSERT INTO `%sarm_users` (email, name, password_hash, role_id, status, invitation_token, invited_by, created_at) VALUES (:email, :name, :password_hash, :role_id, :status, :token, :invited_by, NOW())',
            $this->prefix
        ));
        $stmt->execute([
            'email' => $email,
            'name' => $name,
            'password_hash' => password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT),
            'role_id' => $roleId,
            'status' => 'invited',
            'token' => $token,
            'invited_by' => $invitedBy,
        ]);

        return ['invitation_token' => $token];
    }

    public function acceptInvitation(string $token, string $password): bool
    {
        $stmt = $this->pdo->prepare(sprintf(
            'SELECT id FROM `%sarm_users` WHERE invitation_token = :token AND status = :status LIMIT 1',
            $this->prefix
        ));
        $stmt->execute(['token' => $token, 'status' => 'invited']);
        $userId = $stmt->fetchColumn();

        if (!$userId) {
            return false;
        }

        $update = $this->pdo->prepare(sprintf(
            'UPDATE `%sarm_users` SET password_hash = :hash, status = :status, invitation_token = NULL, updated_at = NOW() WHERE id = :id',
            $this->prefix
        ));

        return $update->execute([
            'hash' => password_hash($password, PASSWORD_DEFAULT),
            'status' => 'active',
            'id' => $userId,
        ]);
    }

    public function requestPasswordReset(string $email): ?string
    {
        $stmt = $this->pdo->prepare(sprintf('SELECT id FROM `%sarm_users` WHERE email = :email LIMIT 1', $this->prefix));
        $stmt->execute(['email' => $email]);
        $userId = $stmt->fetchColumn();

        if (!$userId) {
            return null;
        }

        $token = bin2hex(random_bytes(24));
        $expires = (new DateTimeImmutable())->add(new DateInterval('PT1H'));

        $update = $this->pdo->prepare(sprintf(
            'UPDATE `%sarm_users` SET reset_token = :token, reset_token_expires = :expires WHERE id = :id',
            $this->prefix
        ));
        $update->execute([
            'token' => $token,
            'expires' => $expires->format('Y-m-d H:i:s'),
            'id' => $userId,
        ]);

        return $token;
    }

    public function resetPassword(string $token, string $password): bool
    {
        $stmt = $this->pdo->prepare(sprintf(
            'SELECT id, reset_token_expires FROM `%sarm_users` WHERE reset_token = :token LIMIT 1',
            $this->prefix
        ));
        $stmt->execute(['token' => $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return false;
        }

        if (!empty($user['reset_token_expires']) && strtotime((string) $user['reset_token_expires']) < time()) {
            return false;
        }

        $update = $this->pdo->prepare(sprintf(
            'UPDATE `%sarm_users` SET password_hash = :hash, reset_token = NULL, reset_token_expires = NULL, status = :status WHERE id = :id',
            $this->prefix
        ));

        return $update->execute([
            'hash' => password_hash($password, PASSWORD_DEFAULT),
            'status' => 'active',
            'id' => $user['id'],
        ]);
    }

    public function guardCapability(string $capability): void
    {
        if (!$this->can($capability)) {
            http_response_code(403);
            echo json_encode(['error' => 'forbidden', 'message' => 'You do not have permission.']);
            exit;
        }
    }

    private function primeCurrentUser(): void
    {
        $userId = $this->userFromSession() ?? $this->userFromJwt();
        if ($userId === null) {
            $this->authorization->setCurrentUser(null);
            return;
        }

        $stmt = $this->pdo->prepare(sprintf(
            'SELECT u.*, r.slug AS role_slug FROM `%sarm_users` u JOIN `%sarm_roles` r ON r.id = u.role_id WHERE u.id = :id LIMIT 1',
            $this->prefix,
            $this->prefix
        ));
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $this->authorization->setCurrentUser($user ?: null);
    }

    private function userFromSession(): ?int
    {
        $this->startSession();
        return isset($_SESSION['arm_user_id']) ? (int) $_SESSION['arm_user_id'] : null;
    }

    private function userFromJwt(): ?int
    {
        $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with(strtolower($token), 'bearer ')) {
            $token = trim(substr($token, 7));
        } elseif (!empty($_COOKIE['arm_session'])) {
            $token = (string) $_COOKIE['arm_session'];
        } else {
            return null;
        }

        $claims = $this->jwt->decode($token);
        if (!$claims || !isset($claims['sub'])) {
            return null;
        }

        return (int) $claims['sub'];
    }

    private function sendSessionCookie(string $jwt): void
    {
        setcookie('arm_session', $jwt, [
            'expires' => time() + 7200,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function clearSessionCookie(): void
    {
        setcookie('arm_session', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
                'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            ]);
        }
    }

    private static function configFromEnvironment(): Config
    {
        $env = $_ENV;

        if (defined('DB_HOST')) { $env['DB_HOST'] = DB_HOST; }
        if (defined('DB_PORT')) { $env['DB_PORT'] = DB_PORT; }
        if (defined('DB_NAME')) { $env['DB_NAME'] = DB_NAME; }
        if (defined('DB_USER')) { $env['DB_USER'] = DB_USER; }
        if (defined('DB_PASSWORD')) { $env['DB_PASSWORD'] = DB_PASSWORD; }
        if (defined('DB_CHARSET')) { $env['DB_CHARSET'] = DB_CHARSET; }
        if (defined('DB_COLLATE')) { $env['DB_COLLATE'] = DB_COLLATE; }

        $env['DB_PREFIX'] = $env['DB_PREFIX'] ?? ($_ENV['DB_PREFIX'] ?? 'wp_');

        return Config::fromEnv($env);
    }

    private function publicUser(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'role' => $user['role_slug'] ?? null,
            'status' => $user['status'] ?? 'active',
        ];
    }
}
