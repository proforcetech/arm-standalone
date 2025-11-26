<?php

declare(strict_types=1);

namespace ARM\Migrations;

use PDO;
use RuntimeException;

final class MigrationRunner
{
    private PDO $pdo;
    private string $prefix;
    private string $charsetCollate;

    public function __construct(PDO $pdo, string $prefix, string $charsetCollate)
    {
        $this->pdo            = $pdo;
        $this->prefix         = $prefix;
        $this->charsetCollate = $charsetCollate;
    }

    public function runPending(string $migrationsPath): array
    {
        $this->ensureMigrationsTable();

        $applied = $this->getAppliedMigrations();
        $migrations = $this->loadMigrations($migrationsPath);

        $ran = [];
        foreach ($migrations as $migration) {
            if (in_array($migration->getId(), $applied, true)) {
                continue;
            }

            $this->applyMigration($migration);
            $ran[] = $migration->getId();
        }

        return $ran;
    }

    public function pending(string $migrationsPath): array
    {
        $this->ensureMigrationsTable();

        $applied = $this->getAppliedMigrations();
        $migrations = $this->loadMigrations($migrationsPath);

        return array_values(array_filter(
            array_map(static fn (MigrationInterface $migration) => $migration->getId(), $migrations),
            static fn (string $id) => !in_array($id, $applied, true)
        ));
    }

    private function applyMigration(MigrationInterface $migration): void
    {
        $this->pdo->beginTransaction();
        try {
            $migration->up($this->pdo, $this->prefix, $this->charsetCollate);
            $stmt = $this->pdo->prepare(
                sprintf('INSERT INTO `%sarm_migrations` (migration, applied_at) VALUES (:migration, NOW())', $this->prefix)
            );
            $stmt->execute(['migration' => $migration->getId()]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw new RuntimeException('Migration failed: ' . $exception->getMessage(), 0, $exception);
        }
    }

    private function loadMigrations(string $path): array
    {
        if (!is_dir($path)) {
            return [];
        }

        $files = glob(rtrim($path, '/\\') . '/*.php');
        sort($files);

        $migrations = [];
        foreach ($files as $file) {
            $migration = require $file;
            if (!$migration instanceof MigrationInterface) {
                throw new RuntimeException(sprintf('Migration %s must return a MigrationInterface instance.', $file));
            }
            $migrations[] = $migration;
        }

        return $migrations;
    }

    private function ensureMigrationsTable(): void
    {
        $sql = sprintf(
            'CREATE TABLE IF NOT EXISTS `%sarm_migrations` (id INT UNSIGNED NOT NULL AUTO_INCREMENT, migration VARCHAR(191) NOT NULL, applied_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY uniq_migration (migration)) ENGINE=InnoDB %s;',
            $this->prefix,
            $this->charsetCollate
        );

        $this->pdo->exec($sql);
    }

    private function getAppliedMigrations(): array
    {
        $tableCheck = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table LIMIT 1'
        );
        $tableCheck->execute(['table' => $this->prefix . 'arm_migrations']);
        if ($tableCheck->rowCount() === 0) {
            return [];
        }

        $rows = $this->pdo->query(sprintf('SELECT migration FROM `%sarm_migrations` ORDER BY applied_at ASC', $this->prefix));

        return $rows ? $rows->fetchAll(PDO::FETCH_COLUMN) : [];
    }
}
