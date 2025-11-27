<?php

namespace ARM\Setup;

if (!defined('ABSPATH')) exit;

class SchemaFix {
    public static function run(): void {
        global $db;

        if (!function_exists('arm_require_upgrade_file')) {
            require_once __DIR__ . '/../compat/upgrade.php';
        }

        if (!arm_require_upgrade_file()) {
            return;
        }

        $estimates_table = $db->prefix . 'arm_estimates';
        $jobs_table      = $db->prefix . 'arm_estimate_jobs';
        $time_entries    = $db->prefix . 'arm_time_entries';
        self::addColumn($estimates_table, 'vehicle_id', 'BIGINT UNSIGNED NULL');
        self::addColumn($estimates_table, 'vehicle_year', 'SMALLINT UNSIGNED NULL');
        self::addColumn($estimates_table, 'vehicle_make', 'VARCHAR(80) NULL');
        self::addColumn($estimates_table, 'vehicle_model', 'VARCHAR(120) NULL');
        self::addColumn($estimates_table, 'vehicle_engine', 'VARCHAR(120) NULL');
        self::addColumn($estimates_table, 'vehicle_transmission', 'VARCHAR(80) NULL');
        self::addColumn($estimates_table, 'vehicle_drive', 'VARCHAR(32) NULL');
        self::addColumn($estimates_table, 'vehicle_trim', 'VARCHAR(120) NULL');
        self::addColumn($estimates_table, 'technician_id', 'BIGINT UNSIGNED NULL');
        self::addColumn($jobs_table, 'technician_id', 'BIGINT UNSIGNED NULL');

        self::addColumn($time_entries, 'start_location', 'LONGTEXT NULL');
        self::addColumn($time_entries, 'end_location', 'LONGTEXT NULL');

        self::ensurePrimaryKey($db->prefix . 'arm_customers', 'id');
        self::ensurePrimaryKey($db->prefix . 'arm_appointments', 'id');

        
        self::modifyColumn(
            $db->prefix . 'arm_availability',
            'day_of_week',
            "TINYINT NULL COMMENT '0=Sunday,6=Saturday (for hours)'"
        );
        self::modifyColumn(
            $db->prefix . 'arm_availability',
            'date',
            "DATE NULL COMMENT 'for holiday single day'"
        );

        
        self::addIndex($estimates_table, 'idx_arm_estimates_customer_id', ['customer_id']);
        self::addIndex($estimates_table, 'idx_arm_estimates_request_id', ['request_id']);
        self::addIndex($estimates_table, 'idx_arm_estimates_vehicle_id', ['vehicle_id']);
        self::addIndex($estimates_table, 'idx_arm_estimates_technician_id', ['technician_id']);

        self::addIndex($db->prefix . 'arm_estimate_jobs', 'idx_arm_estimate_jobs_estimate_id', ['estimate_id']);
        self::addIndex($db->prefix . 'arm_estimate_jobs', 'idx_arm_estimate_jobs_technician_id', ['technician_id']);

        self::addIndex($db->prefix . 'arm_estimate_items', 'idx_arm_estimate_items_estimate_id', ['estimate_id']);
        self::addIndex($db->prefix . 'arm_estimate_items', 'idx_arm_estimate_items_job_id', ['job_id']);

        self::addIndex($db->prefix . 'arm_invoices', 'idx_arm_invoices_customer_id', ['customer_id']);
        self::addIndex($db->prefix . 'arm_invoices', 'idx_arm_invoices_estimate_id', ['estimate_id']);

        self::addIndex($db->prefix . 'arm_invoice_items', 'idx_arm_invoice_items_invoice_id', ['invoice_id']);

        self::addIndex($db->prefix . 'arm_service_bundles', 'idx_arm_service_bundles_service_type_id', ['service_type_id']);
        self::addIndex($db->prefix . 'arm_service_bundles', 'idx_arm_service_bundles_is_active', ['is_active']);

        self::addIndex($db->prefix . 'arm_service_bundle_items', 'idx_arm_service_bundle_items_bundle_id', ['bundle_id']);
    }

    private static function ensurePrimaryKey(string $table, string $column): void {
        global $db;
        $hasPk = $db->get_var(
            $db->prepare(
                "SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
                 WHERE CONSTRAINT_TYPE='PRIMARY KEY'
                   AND TABLE_SCHEMA=DATABASE()
                   AND TABLE_NAME=%s
                 LIMIT 1",
                 $table
            )
        );
        if (!$hasPk) {
            
            $db->query("ALTER TABLE `$table` ADD PRIMARY KEY (`$column`)");
        }
    }

    private static function modifyColumn(string $table, string $column, string $definition): void {
        global $db;

        $exists = $db->get_var(
            $db->prepare(
                "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA=DATABASE()
                   AND TABLE_NAME=%s
                   AND COLUMN_NAME=%s
                 LIMIT 1",
                $table, $column
            )
        );
        if ($exists) {
            $db->query("ALTER TABLE `$table` MODIFY COLUMN `$column` $definition");
        }
    }

    private static function addColumn(string $table, string $column, string $definition): void {
        global $db;

        $column = sanitize_key($column);
        $definition = trim($definition);
        $table_clean = preg_replace('/[^A-Za-z0-9_]/', '', $table);
        if ($column === '' || $definition === '' || $table_clean === '') {
            return;
        }

        $exists = $db->get_var(
            $db->prepare(
                "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA=DATABASE()
                   AND TABLE_NAME=%s
                   AND COLUMN_NAME=%s
                 LIMIT 1",
                $table_clean,
                $column
            )
        );
        if ($exists) {
            return;
        }

        $db->query("ALTER TABLE `$table_clean` ADD COLUMN `$column` $definition");
    }

    private static function addIndex(string $table, string $indexName, array $columns, string $type = 'INDEX'): void {
        global $db;
        if (empty($columns)) return;


        $exists = $db->get_var(
            $db->prepare(
                "SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA=DATABASE()
                   AND TABLE_NAME=%s
                   AND INDEX_NAME=%s
                 LIMIT 1",
                $table, $indexName
            )
        );
        if ($exists) return;

        
        $cols = implode('`,`', array_map('sanitize_key', $columns));
        $sql  = "ALTER TABLE `$table` ADD $type `$indexName` (`$cols`)";
        $db->query($sql);
    }
}
