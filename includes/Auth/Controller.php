<?php

declare(strict_types=1);

namespace ARM\Auth;

final class Controller
{
    public static function login(): array
    {
        $payload = self::payload();
        $email = $payload['email'] ?? '';
        $password = $payload['password'] ?? '';

        $service = AuthService::fromEnvironment();
        $result = $service->login($email, $password);

        if (!$result) {
            http_response_code(401);
            return ['error' => 'invalid_credentials'];
        }

        return $result;
    }

    public static function logout(): array
    {
        $service = AuthService::fromEnvironment();
        $service->logout();

        return ['status' => 'logged_out'];
    }

    public static function me(): array
    {
        $service = AuthService::fromEnvironment();
        $user = $service->currentUser();

        if (!$user) {
            http_response_code(401);
            return ['error' => 'unauthenticated'];
        }

        return ['user' => [
            'id' => (int) $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'role' => $user['role_slug'] ?? null,
            'status' => $user['status'] ?? 'active',
        ]];
    }

    public static function requestReset(): array
    {
        $payload = self::payload();
        $email = $payload['email'] ?? '';
        $service = AuthService::fromEnvironment();
        $token = $service->requestPasswordReset($email);

        if (!$token) {
            http_response_code(404);
            return ['error' => 'not_found'];
        }

        return ['reset_token' => $token];
    }

    public static function resetPassword(): array
    {
        $payload = self::payload();
        $token = $payload['token'] ?? '';
        $password = $payload['password'] ?? '';
        $service = AuthService::fromEnvironment();
        $ok = $service->resetPassword($token, $password);

        if (!$ok) {
            http_response_code(400);
            return ['error' => 'invalid_token'];
        }

        return ['status' => 'password_reset'];
    }

    public static function invite(): array
    {
        $service = AuthService::fromEnvironment();
        $service->guardCapability('manage_options');

        $payload = self::payload();
        $email = $payload['email'] ?? '';
        $name = $payload['name'] ?? $email;
        $roleId = (int) ($payload['role_id'] ?? 0);

        $current = $service->currentUser();
        $invited = $service->inviteUser($email, $name, $roleId, $current['id'] ?? null);

        return $invited;
    }

    public static function acceptInvitation(): array
    {
        $payload = self::payload();
        $token = $payload['token'] ?? '';
        $password = $payload['password'] ?? '';

        $service = AuthService::fromEnvironment();
        $ok = $service->acceptInvitation($token, $password);

        if (!$ok) {
            http_response_code(400);
            return ['error' => 'invalid_invitation'];
        }

        return ['status' => 'accepted'];
    }

    private static function payload(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        return $_POST;
    }
}
