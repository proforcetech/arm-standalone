<?php
/**
 * Auto-initialize $db when accessed
 */

declare(strict_types=1);

if (!function_exists('arm_ensure_db')) {
    function arm_ensure_db(): void
    {
        global $db;

        if ($db === null || !($db instanceof db)) {
            try {
                $db = db::getInstance();
            } catch (Exception $e) {
                // Database not configured - log error but don't fatal
                error_log('ARM Database Error: ' . $e->getMessage());

                // Create a stub db that won't crash
                if (!class_exists('db_stub')) {
                    class db_stub {
                        public string $prefix = '';
                        public ?int $insert_id = null;
                        public ?int $rows_affected = null;
                        public ?string $last_error = 'Database not configured';
                        public ?string $last_query = null;

                        public function __call($method, $args) {
                            $this->last_error = 'Database not configured';
                            return null;
                        }
                    }
                }

                $db = new db_stub();
            }
        }
    }
}

// Ensure db is available immediately
arm_ensure_db();
