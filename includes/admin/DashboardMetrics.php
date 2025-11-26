<?php
namespace ARM\Admin;

use db;

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/Inventory.php';

/**
 * Collection of helper queries that power the admin dashboard KPIs.
 * Each method defends against missing tables and normalises return structures
 * so they can be asserted in isolation during unit tests.
 */
final class DashboardMetrics
{
    private const SMS_TABLE_CANDIDATES = [
        'arm_sms_logs',
        'arm_sms_log',
        'arm_twilio_logs',
        'arm_twilio_log',
        'arm_message_log',
    ];

    public static function estimate_counts(db $db): array
    {
        $table = $db->prefix . 'arm_estimates';
        if (!self::table_exists($db, $table)) {
            return [
                'exists'   => false,
                'pending'  => 0,
                'approved' => 0,
                'rejected' => 0,
            ];
        }

        return [
            'exists'   => true,
            'pending'  => (int) $db->get_var("SELECT COUNT(*) FROM $table WHERE status='PENDING'"),
            'approved' => (int) $db->get_var("SELECT COUNT(*) FROM $table WHERE status='APPROVED'"),
            'rejected' => (int) $db->get_var("SELECT COUNT(*) FROM $table WHERE status='REJECTED'"),
        ];
    }

    public static function invoice_counts(db $db): array
    {
        $table = $db->prefix . 'arm_invoices';
        if (!self::table_exists($db, $table)) {
            return [
                'exists'      => false,
                'total'       => 0,
                'paid'        => 0,
                'unpaid'      => 0,
                'void'        => 0,
                'avg_paid'    => 0.0,
                'sum_paid'    => 0.0,
                'sum_unpaid'  => 0.0,
                'sum_tax'     => 0.0,
            ];
        }

        return [
            'exists'      => true,
            'total'       => (int) $db->get_var("SELECT COUNT(*) FROM $table"),
            'paid'        => (int) $db->get_var("SELECT COUNT(*) FROM $table WHERE status='PAID'"),
            'unpaid'      => (int) $db->get_var("SELECT COUNT(*) FROM $table WHERE status='UNPAID'"),
            'void'        => (int) $db->get_var("SELECT COUNT(*) FROM $table WHERE status='VOID'"),
            'avg_paid'    => (float) $db->get_var("SELECT AVG(total) FROM $table WHERE status='PAID'"),
            'sum_paid'    => (float) $db->get_var("SELECT SUM(total) FROM $table WHERE status='PAID'"),
            'sum_unpaid'  => (float) $db->get_var("SELECT SUM(total) FROM $table WHERE status='UNPAID'"),
            'sum_tax'     => (float) $db->get_var("SELECT SUM(tax_amount) FROM $table WHERE status='PAID'"),
        ];
    }

    public static function invoice_monthly_totals(db $db, int $months = 6): array
    {
        $table = $db->prefix . 'arm_invoices';
        if (!self::table_exists($db, $table)) {
            return [
                'labels' => [],
                'totals' => [],
            ];
        }

        $sql = $db->prepare(
            "SELECT DATE_FORMAT(created_at,'%%Y-%%m') AS ym, SUM(total) AS total
             FROM $table WHERE status='PAID'
             GROUP BY ym ORDER BY ym DESC LIMIT %d",
            max(1, $months)
        );

        $rows   = $db->get_results($sql);
        $labels = [];
        $totals = [];
        foreach (array_reverse($rows ?: []) as $row) {
            $labels[] = (string) $row->ym;
            $totals[] = (float) $row->total;
        }

        return compact('labels', 'totals');
    }

    public static function estimate_trends(db $db, int $months = 6): array
    {
        $table = $db->prefix . 'arm_estimates';
        if (!self::table_exists($db, $table)) {
            return [
                'labels'   => [],
                'approved' => [],
                'rejected' => [],
            ];
        }

        $sql = $db->prepare(
            "SELECT DATE_FORMAT(created_at,'%%Y-%%m') AS ym,
                    SUM(CASE WHEN status='APPROVED' THEN 1 ELSE 0 END) AS approved,
                    SUM(CASE WHEN status='REJECTED' THEN 1 ELSE 0 END) AS rejected
             FROM $table GROUP BY ym ORDER BY ym DESC LIMIT %d",
            max(1, $months)
        );

        $rows     = $db->get_results($sql);
        $labels   = [];
        $approved = [];
        $rejected = [];
        foreach (array_reverse($rows ?: []) as $row) {
            $labels[]   = (string) $row->ym;
            $approved[] = (int) $row->approved;
            $rejected[] = (int) $row->rejected;
        }

        return compact('labels', 'approved', 'rejected');
    }

    public static function inventory_value(db $db): array
    {
        $table = $db->prefix . 'arm_inventory';
        if (!self::table_exists($db, $table)) {
            return [
                'exists' => false,
                'value'  => 0.0,
            ];
        }

        $cols = Inventory::schema_columns($table);
        $qty  = $cols['qty'] ?? 'qty_on_hand';
        $price = $cols['price'] ?? 'price';

        $sql = "SELECT SUM(COALESCE($qty,0) * COALESCE($price,0)) FROM $table";
        $value = (float) $db->get_var($sql);

        return [
            'exists' => true,
            'value'  => $value,
        ];
    }

    public static function warranty_claim_counts(db $db): array
    {
        $table = $db->prefix . 'arm_warranty_claims';
        if (!self::table_exists($db, $table)) {
            return [
                'exists'  => false,
                'open'    => 0,
                'resolved'=> 0,
            ];
        }

        $sql = "SELECT
                    SUM(CASE WHEN UPPER(status) IN ('RESOLVED','CLOSED') THEN 1 ELSE 0 END) AS resolved,
                    SUM(CASE WHEN UPPER(status) IN ('RESOLVED','CLOSED') THEN 0 ELSE 1 END) AS open
                FROM $table";
        $row = $db->get_row($sql);

        return [
            'exists'   => true,
            'open'     => (int) ($row->open ?? 0),
            'resolved' => (int) ($row->resolved ?? 0),
        ];
    }

    public static function sms_totals(db $db): array
    {
        $table = self::resolve_sms_table($db);
        if (!$table) {
            return [
                'exists'   => false,
                'channels' => [],
            ];
        }

        $columns = self::column_map($db, $table, [
            'status'  => ['status', 'delivery_status', 'state'],
            'channel' => ['channel', 'context', 'category', 'hook'],
        ]);

        if (empty($columns['status'])) {
            return [
                'exists'   => true,
                'channels' => [],
            ];
        }

        $statusCol  = $columns['status'];
        $channelCol = $columns['channel'];

        if ($channelCol) {
            $sql = "SELECT COALESCE($channelCol, 'unknown') AS channel,
                           SUM(CASE WHEN UPPER($statusCol)='SENT' THEN 1 ELSE 0 END) AS sent,
                           SUM(CASE WHEN UPPER($statusCol)='DELIVERED' THEN 1 ELSE 0 END) AS delivered,
                           SUM(CASE WHEN UPPER($statusCol)='FAILED' THEN 1 ELSE 0 END) AS failed
                    FROM $table GROUP BY channel ORDER BY channel";
            $rows = $db->get_results($sql);
            $channels = [];
            foreach ($rows ?: [] as $row) {
                $label = (string) $row->channel;
                $channels[$label] = [
                    'sent'      => (int) $row->sent,
                    'delivered' => (int) $row->delivered,
                    'failed'    => (int) $row->failed,
                ];
            }
        } else {
            $sql = "SELECT
                        SUM(CASE WHEN UPPER($statusCol)='SENT' THEN 1 ELSE 0 END) AS sent,
                        SUM(CASE WHEN UPPER($statusCol)='DELIVERED' THEN 1 ELSE 0 END) AS delivered,
                        SUM(CASE WHEN UPPER($statusCol)='FAILED' THEN 1 ELSE 0 END) AS failed
                    FROM $table";
            $row = $db->get_row($sql);
            $channels = [
                __('All Channels', 'arm-repair-estimates') => [
                    'sent'      => (int) ($row->sent ?? 0),
                    'delivered' => (int) ($row->delivered ?? 0),
                    'failed'    => (int) ($row->failed ?? 0),
                ],
            ];
        }

        return [
            'exists'   => true,
            'channels' => $channels,
        ];
    }

    private static function table_exists(db $db, string $table): bool
    {
        $like = $db->esc_like($table);
        return (bool) $db->get_var($db->prepare('SHOW TABLES LIKE %s', $like));
    }

    private static function resolve_sms_table(db $db): ?string
    {
        foreach (self::SMS_TABLE_CANDIDATES as $candidate) {
            $table = $db->prefix . $candidate;
            if (self::table_exists($db, $table)) {
                return $table;
            }
        }
        return null;
    }

    private static function column_map(db $db, string $table, array $map): array
    {
        $cols = $db->get_col(
            $db->prepare(
                "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s",
                $table
            )
        ) ?: [];
        $lookup = array_change_key_case(array_flip($cols), CASE_LOWER);
        $picked = [];
        foreach ($map as $key => $candidates) {
            $picked[$key] = null;
            foreach ($candidates as $candidate) {
                $normalized = strtolower($candidate);
                if (isset($lookup[$normalized])) {
                    $picked[$key] = $candidate;
                    break;
                }
            }
        }
        return $picked;
    }
}
