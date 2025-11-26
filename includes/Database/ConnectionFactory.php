<?php

declare(strict_types=1);

namespace ARM\Database;

use PDO;
use PDOException;
use RuntimeException;

final class ConnectionFactory
{
    public static function make(Config $config): PDO
    {
        try {
            $pdo = new PDO(
                $config->dsn(),
                $config->username(),
                $config->password(),
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (PDOException $exception) {
            throw new RuntimeException('Unable to connect to the database: ' . $exception->getMessage(), 0, $exception);
        }

        return $pdo;
    }
}
