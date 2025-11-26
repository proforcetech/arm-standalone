<?php

declare(strict_types=1);

namespace ARM\Migrations;

use PDO;

interface SeederInterface
{
    public function getId(): string;

    public function run(PDO $pdo, string $prefix): void;
}
