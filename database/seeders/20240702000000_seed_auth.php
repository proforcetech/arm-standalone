<?php

declare(strict_types=1);

use ARM\Migrations\SeederInterface;
use PDO;

return new class implements SeederInterface {
    public function getId(): string
    {
        return '20240702000000_seed_auth';
    }

    public function run(PDO $pdo, string $prefix): void
    {
        $adminRoleId = $this->ensureRole($pdo, $prefix, 'Administrator', 'admin');
        $this->ensureCapability($pdo, $prefix, $adminRoleId, 'manage_options');

        $email = $_ENV['ADMIN_EMAIL'] ?? 'admin@example.com';
        $name = $_ENV['ADMIN_NAME'] ?? 'Administrator';
        $password = $_ENV['ADMIN_PASSWORD'] ?? 'change-me-now';

        $stmt = $pdo->prepare(sprintf('SELECT id FROM `%sarm_users` WHERE email = :email LIMIT 1', $prefix));
        $stmt->execute(['email' => $email]);
        $userId = $stmt->fetchColumn();

        if (!$userId) {
            $insert = $pdo->prepare(sprintf(
                'INSERT INTO `%sarm_users` (email, name, password_hash, role_id, status, created_at) VALUES (:email, :name, :password_hash, :role_id, :status, NOW())',
                $prefix
            ));

            $insert->execute([
                'email' => $email,
                'name' => $name,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role_id' => $adminRoleId,
                'status' => 'active',
            ]);
        }
    }

    private function ensureRole(PDO $pdo, string $prefix, string $name, string $slug): int
    {
        $stmt = $pdo->prepare(sprintf('SELECT id FROM `%sarm_roles` WHERE slug = :slug LIMIT 1', $prefix));
        $stmt->execute(['slug' => $slug]);
        $roleId = $stmt->fetchColumn();

        if ($roleId) {
            return (int) $roleId;
        }

        $insert = $pdo->prepare(sprintf(
            'INSERT INTO `%sarm_roles` (name, slug, created_at) VALUES (:name, :slug, NOW())',
            $prefix
        ));
        $insert->execute(['name' => $name, 'slug' => $slug]);

        return (int) $pdo->lastInsertId();
    }

    private function ensureCapability(PDO $pdo, string $prefix, int $roleId, string $capability): void
    {
        $stmt = $pdo->prepare(sprintf(
            'SELECT id FROM `%sarm_role_permissions` WHERE role_id = :role_id AND capability = :cap LIMIT 1',
            $prefix
        ));
        $stmt->execute(['role_id' => $roleId, 'cap' => $capability]);

        if ($stmt->fetchColumn()) {
            return;
        }

        $insert = $pdo->prepare(sprintf(
            'INSERT INTO `%sarm_role_permissions` (role_id, capability, created_at) VALUES (:role_id, :capability, NOW())',
            $prefix
        ));
        $insert->execute(['role_id' => $roleId, 'capability' => $capability]);
    }
};
