<?php
/**
 * Auto-initialize $wpdb when accessed
 */

declare(strict_types=1);

if (!function_exists('arm_ensure_wpdb')) {
    function arm_ensure_wpdb(): void
    {
        global $wpdb;

        if ($wpdb === null || !($wpdb instanceof wpdb)) {
            try {
                $wpdb = wpdb::getInstance();
            } catch (Exception $e) {
                // Database not configured - log error but don't fatal
                error_log('ARM Database Error: ' . $e->getMessage());

                // Create a stub wpdb that won't crash
                if (!class_exists('wpdb_stub')) {
                    class wpdb_stub {
                        public string $prefix = 'wp_';
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

                $wpdb = new wpdb_stub();
            }
        }
    }
}

// Ensure wpdb is available immediately
arm_ensure_wpdb();
