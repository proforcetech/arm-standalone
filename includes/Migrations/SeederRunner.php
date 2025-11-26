<?php

declare(strict_types=1);

namespace ARM\Migrations;

use PDO;
use RuntimeException;

final class SeederRunner
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

    public function runPending(string $seedersPath): array
    {
        $this->ensureSeedersTable();
        $applied = $this->getAppliedSeeders();
        $seeders = $this->loadSeeders($seedersPath);

        $ran = [];
        foreach ($seeders as $seeder) {
            if (in_array($seeder->getId(), $applied, true)) {
                continue;
            }

            $this->applySeeder($seeder);
            $ran[] = $seeder->getId();
        }

        return $ran;
    }

    public function pending(string $seedersPath): array
    {
        $this->ensureSeedersTable();

        $applied = $this->getAppliedSeeders();
        $seeders = $this->loadSeeders($seedersPath);

        return array_values(array_filter(
            array_map(static fn (SeederInterface $seeder) => $seeder->getId(), $seeders),
            static fn (string $id) => !in_array($id, $applied, true)
        ));
    }

    private function applySeeder(SeederInterface $seeder): void
    {
        $this->pdo->beginTransaction();
        try {
            $seeder->run($this->pdo, $this->prefix);
            $stmt = $this->pdo->prepare(
                sprintf('INSERT INTO `%sarm_seeders` (seeder, ran_at) VALUES (:seeder, NOW())', $this->prefix)
            );
            $stmt->execute(['seeder' => $seeder->getId()]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            // Check if transaction is still active before attempting rollback
            // Some DDL statements (like CREATE TABLE) auto-commit in MySQL
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new RuntimeException('Seeder failed: ' . $exception->getMessage(), 0, $exception);
        }
    }

    private function loadSeeders(string $path): array
    {
        if (!is_dir($path)) {
            return [];
        }

        $files = glob(rtrim($path, '/\\') . '/*.php');
        sort($files);

        $seeders = [];
        foreach ($files as $file) {
            $seeder = require $file;
            if (!$seeder instanceof SeederInterface) {
                throw new RuntimeException(sprintf('Seeder %s must return a SeederInterface instance.', $file));
            }
            $seeders[] = $seeder;
        }

        return $seeders;
    }

    private function ensureSeedersTable(): void
    {
        $sql = sprintf(
            'CREATE TABLE IF NOT EXISTS `%sarm_seeders` (id INT UNSIGNED NOT NULL AUTO_INCREMENT, seeder VARCHAR(191) NOT NULL, ran_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY uniq_seeder (seeder)) ENGINE=InnoDB %s;',
            $this->prefix,
            $this->charsetCollate
        );

        $this->pdo->exec($sql);
    }

    private function getAppliedSeeders(): array
    {
        $tableCheck = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table LIMIT 1'
        );
        $tableCheck->execute(['table' => $this->prefix . 'arm_seeders']);
        if ($tableCheck->rowCount() === 0) {
            return [];
        }

        $rows = $this->pdo->query(sprintf('SELECT seeder FROM `%sarm_seeders` ORDER BY ran_at ASC', $this->prefix));

        return $rows ? $rows->fetchAll(PDO::FETCH_COLUMN) : [];
    }
}
