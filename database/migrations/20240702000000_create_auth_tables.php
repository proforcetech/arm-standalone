<?php

declare(strict_types=1);

use ARM\Migrations\MigrationInterface;
use PDO;

return new class implements MigrationInterface {
    public function getId(): string
    {
        return '20240702000000_create_auth_tables';
    }

    public function up(PDO $pdo, string $prefix, string $charsetCollate): void
    {
        $tables = [
            'roles' => "CREATE TABLE IF NOT EXISTS `{$prefix}arm_roles` (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL,
                slug VARCHAR(100) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_slug (slug)
            ) %s;",
            'role_permissions' => "CREATE TABLE IF NOT EXISTS `{$prefix}arm_role_permissions` (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                role_id BIGINT UNSIGNED NOT NULL,
                capability VARCHAR(150) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_role (role_id),
                KEY idx_capability (capability)
            ) %s;",
            'users' => "CREATE TABLE IF NOT EXISTS `{$prefix}arm_users` (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                email VARCHAR(190) NOT NULL,
                name VARCHAR(190) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                role_id BIGINT UNSIGNED NOT NULL,
                status ENUM('active','invited','disabled') NOT NULL DEFAULT 'invited',
                invitation_token VARCHAR(190) NULL,
                reset_token VARCHAR(190) NULL,
                reset_token_expires DATETIME NULL,
                invited_by BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_email (email),
                KEY idx_role (role_id),
                KEY idx_status (status),
                KEY idx_invite (invitation_token),
                KEY idx_reset (reset_token)
            ) %s;",
        ];

        foreach ($tables as $sql) {
            $pdo->exec(sprintf($sql, $charsetCollate));
        }
    }
};
