<?php
/**
 * WordPress Database Layer Compatibility
 * Mimics WordPress db class using PDO
 */

declare(strict_types=1);

use ARM\Database\Config;
use ARM\Database\ConnectionFactory;

if (!class_exists('db')) {
    class db
    {
        private static ?db $instance = null;
        private PDO $pdo;
        public string $prefix;
        public ?int $insert_id = null;
        public ?int $rows_affected = null;
        public ?string $last_error = null;
        public ?string $last_query = null;
        public bool $show_errors = false;
        public bool $suppress_errors = false;

        private function __construct(Config $config)
        {
            $this->pdo = ConnectionFactory::make($config);
            $this->prefix = $config->getPrefix();
        }

        public static function getInstance(): self
        {
            if (self::$instance === null) {
                $config = Config::fromEnv($_ENV);
                self::$instance = new self($config);
            }
            return self::$instance;
        }

        /**
         * Prepare a SQL query with placeholders
         */
        public function prepare(string $query, ...$args): string
        {
            if (empty($args)) {
                return $query;
            }

            // Flatten array if first arg is an array
            if (count($args) === 1 && is_array($args[0])) {
                $args = $args[0];
            }

            $query = str_replace('%d', '%s', $query);
            $query = str_replace('%f', '%s', $query);

            // Replace %s with quoted values
            foreach ($args as $arg) {
                if ($arg === null) {
                    $escaped = 'NULL';
                } elseif (is_int($arg) || is_float($arg)) {
                    $escaped = (string) $arg;
                } else {
                    $escaped = $this->pdo->quote((string) $arg);
                }

                $pos = strpos($query, '%s');
                if ($pos !== false) {
                    $query = substr_replace($query, $escaped, $pos, 2);
                }
            }

            return $query;
        }

        /**
         * Execute a query and return the result
         */
        public function query(string $query)
        {
            $this->last_query = $query;
            $this->last_error = null;
            $this->rows_affected = 0;

            try {
                $stmt = $this->pdo->query($query);

                if ($stmt === false) {
                    $this->handleError();
                    return false;
                }

                $this->rows_affected = $stmt->rowCount();
                $this->insert_id = (int) $this->pdo->lastInsertId() ?: null;

                return $stmt;
            } catch (PDOException $e) {
                $this->last_error = $e->getMessage();
                if ($this->show_errors && !$this->suppress_errors) {
                    trigger_error($this->last_error, E_USER_WARNING);
                }
                return false;
            }
        }

        /**
         * Get a single row from the database
         */
        public function get_row(?string $query = null, string $output = OBJECT, int $y = 0)
        {
            if ($query !== null) {
                $this->query($query);
            }

            if ($this->last_query === null) {
                return null;
            }

            try {
                $stmt = $this->pdo->query($this->last_query);
                if ($stmt === false) {
                    return null;
                }

                $fetchMode = $output === ARRAY_A ? PDO::FETCH_ASSOC :
                            ($output === ARRAY_N ? PDO::FETCH_NUM : PDO::FETCH_OBJ);

                $rows = $stmt->fetchAll($fetchMode);
                return $rows[$y] ?? null;
            } catch (PDOException $e) {
                $this->last_error = $e->getMessage();
                return null;
            }
        }

        /**
         * Get a single column value from the database
         */
        public function get_var(?string $query = null, int $x = 0, int $y = 0)
        {
            if ($query !== null) {
                $this->query($query);
            }

            if ($this->last_query === null) {
                return null;
            }

            try {
                $stmt = $this->pdo->query($this->last_query);
                if ($stmt === false) {
                    return null;
                }

                $rows = $stmt->fetchAll(PDO::FETCH_NUM);
                return $rows[$y][$x] ?? null;
            } catch (PDOException $e) {
                $this->last_error = $e->getMessage();
                return null;
            }
        }

        /**
         * Get multiple rows from the database
         */
        public function get_results(?string $query = null, string $output = OBJECT): ?array
        {
            if ($query !== null) {
                $this->query($query);
            }

            if ($this->last_query === null) {
                return null;
            }

            try {
                $stmt = $this->pdo->query($this->last_query);
                if ($stmt === false) {
                    return null;
                }

                $fetchMode = $output === ARRAY_A ? PDO::FETCH_ASSOC :
                            ($output === ARRAY_N ? PDO::FETCH_NUM : PDO::FETCH_OBJ);

                return $stmt->fetchAll($fetchMode);
            } catch (PDOException $e) {
                $this->last_error = $e->getMessage();
                return null;
            }
        }

        /**
         * Get a single column from the database
         */
        public function get_col(?string $query = null, int $x = 0): ?array
        {
            if ($query !== null) {
                $this->query($query);
            }

            if ($this->last_query === null) {
                return null;
            }

            try {
                $stmt = $this->pdo->query($this->last_query);
                if ($stmt === false) {
                    return null;
                }

                $rows = $stmt->fetchAll(PDO::FETCH_NUM);
                return array_column($rows, $x);
            } catch (PDOException $e) {
                $this->last_error = $e->getMessage();
                return null;
            }
        }

        /**
         * Insert a row into the database
         */
        public function insert(string $table, array $data, $format = null): bool
        {
            $fields = array_keys($data);
            $values = array_values($data);

            $placeholders = array_fill(0, count($values), '?');

            $sql = sprintf(
                "INSERT INTO %s (%s) VALUES (%s)",
                $table,
                implode(', ', $fields),
                implode(', ', $placeholders)
            );

            try {
                $stmt = $this->pdo->prepare($sql);
                $result = $stmt->execute($values);

                if ($result) {
                    $this->insert_id = (int) $this->pdo->lastInsertId();
                    $this->rows_affected = $stmt->rowCount();
                }

                return $result;
            } catch (PDOException $e) {
                $this->last_error = $e->getMessage();
                if ($this->show_errors && !$this->suppress_errors) {
                    trigger_error($this->last_error, E_USER_WARNING);
                }
                return false;
            }
        }

        /**
         * Update rows in the database
         */
        public function update(string $table, array $data, array $where, $format = null, $where_format = null): bool
        {
            $sets = [];
            $values = [];

            foreach ($data as $field => $value) {
                $sets[] = "$field = ?";
                $values[] = $value;
            }

            $conditions = [];
            foreach ($where as $field => $value) {
                $conditions[] = "$field = ?";
                $values[] = $value;
            }

            $sql = sprintf(
                "UPDATE %s SET %s WHERE %s",
                $table,
                implode(', ', $sets),
                implode(' AND ', $conditions)
            );

            try {
                $stmt = $this->pdo->prepare($sql);
                $result = $stmt->execute($values);

                if ($result) {
                    $this->rows_affected = $stmt->rowCount();
                }

                return $result;
            } catch (PDOException $e) {
                $this->last_error = $e->getMessage();
                if ($this->show_errors && !$this->suppress_errors) {
                    trigger_error($this->last_error, E_USER_WARNING);
                }
                return false;
            }
        }

        /**
         * Delete rows from the database
         */
        public function delete(string $table, array $where, $where_format = null): bool
        {
            $conditions = [];
            $values = [];

            foreach ($where as $field => $value) {
                $conditions[] = "$field = ?";
                $values[] = $value;
            }

            $sql = sprintf(
                "DELETE FROM %s WHERE %s",
                $table,
                implode(' AND ', $conditions)
            );

            try {
                $stmt = $this->pdo->prepare($sql);
                $result = $stmt->execute($values);

                if ($result) {
                    $this->rows_affected = $stmt->rowCount();
                }

                return $result;
            } catch (PDOException $e) {
                $this->last_error = $e->getMessage();
                if ($this->show_errors && !$this->suppress_errors) {
                    trigger_error($this->last_error, E_USER_WARNING);
                }
                return false;
            }
        }

        public function show_errors(): void
        {
            $this->show_errors = true;
        }

        public function hide_errors(): void
        {
            $this->show_errors = false;
        }

        public function suppress_errors(bool $suppress = true): void
        {
            $this->suppress_errors = $suppress;
        }

        public function print_error(): void
        {
            if ($this->last_error) {
                echo '<div class="error"><p>' . htmlspecialchars($this->last_error) . '</p></div>';
            }
        }

        private function handleError(): void
        {
            $errorInfo = $this->pdo->errorInfo();
            $this->last_error = $errorInfo[2] ?? 'Unknown error';

            if ($this->show_errors && !$this->suppress_errors) {
                trigger_error($this->last_error, E_USER_WARNING);
            }
        }

        public function esc_like(string $text): string
        {
            return addcslashes($text, '_%\\');
        }
    }
}

// Define output format constants
if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
if (!defined('ARRAY_N')) {
    define('ARRAY_N', 'ARRAY_N');
}
