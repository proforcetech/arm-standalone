<?php

declare(strict_types=1);

use ARM\Migrations\SeederInterface;
use PDO;

return new class implements SeederInterface {
    public function getId(): string
    {
        return '20240701001000_seed_service_types';
    }

    public function run(PDO $pdo, string $prefix): void
    {
        $table = sprintf('`%sarm_service_types`', $prefix);
        $count = (int) $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        if ($count > 0) {
            return;
        }

        $stmt = $pdo->prepare("INSERT INTO $table (name, is_active, sort_order, created_at) VALUES (:name, 1, :sort, NOW())");
        $defaults = [
            ['name' => 'General Diagnostics', 'sort' => 10],
            ['name' => 'Brake Service', 'sort' => 20],
            ['name' => 'AC Service', 'sort' => 30],
        ];

        foreach ($defaults as $row) {
            $stmt->execute($row);
        }
    }
};
