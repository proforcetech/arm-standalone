<?php

declare(strict_types=1);

namespace ARM\Auth;

use PDO;

final class Authorization
{
    private PDO $pdo;
    private string $prefix;
    private ?array $currentUser = null;

    public function __construct(PDO $pdo, string $prefix)
    {
        $this->pdo    = $pdo;
        $this->prefix = $prefix;
    }

    public function setCurrentUser(?array $user): void
    {
        $this->currentUser = $user;
    }

    public function user(): ?array
    {
        return $this->currentUser;
    }

    public function can(string $capability): bool
    {
        $user = $this->currentUser;
        if (!$user) {
            return false;
        }

        $stmt = $this->pdo->prepare(sprintf(
            'SELECT 1 FROM `%sarm_role_permissions` WHERE role_id = :role_id AND capability = :cap LIMIT 1',
            $this->prefix
        ));
        $stmt->execute([
            'role_id' => $user['role_id'],
            'cap' => $capability,
        ]);

        return $stmt->fetchColumn() !== false;
    }
}
