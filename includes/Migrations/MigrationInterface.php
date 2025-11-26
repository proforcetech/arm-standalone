<?php

declare(strict_types=1);

namespace ARM\Migrations;

use PDO;

interface MigrationInterface
{
    public function getId(): string;

    public function up(PDO $pdo, string $prefix, string $charsetCollate): void;
}
