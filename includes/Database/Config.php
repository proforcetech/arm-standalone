<?php

declare(strict_types=1);

namespace ARM\Database;

final class Config
{
    private string $host;
    private int $port;
    private string $database;
    private string $user;
    private string $password;
    private string $prefix;
    private string $charset;
    private string $collation;

    private function __construct(
        string $host,
        int $port,
        string $database,
        string $user,
        string $password,
        string $prefix,
        string $charset,
        string $collation
    ) {
        $this->host      = $host;
        $this->port      = $port;
        $this->database  = $database;
        $this->user      = $user;
        $this->password  = $password;
        $this->prefix    = rtrim($prefix, '_') . '_';
        $this->charset   = $charset;
        $this->collation = $collation;
    }

    public static function fromEnv(array $env): self
    {
        $host      = $env['DB_HOST'] ?? '127.0.0.1';
        $port      = (int) ($env['DB_PORT'] ?? 3306);
        $database  = $env['DB_NAME'] ?? '';
        $user      = $env['DB_USER'] ?? '';
        $password  = $env['DB_PASSWORD'] ?? '';
        $prefix    = $env['DB_PREFIX'] ?? '';
        $charset   = $env['DB_CHARSET'] ?? 'utf8mb4';
        $collation = $env['DB_COLLATE'] ?? 'utf8mb4_unicode_ci';

        return new self($host, $port, $database, $user, $password, $prefix, $charset, $collation);
    }

    public function dsn(): string
    {
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->host,
            $this->port,
            $this->database,
            $this->charset
        );
    }

    public function username(): string
    {
        return $this->user;
    }

    public function password(): string
    {
        return $this->password;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function charsetCollate(): string
    {
        return sprintf('DEFAULT CHARACTER SET %s COLLATE %s', $this->charset, $this->collation);
    }
}
