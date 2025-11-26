<?php
namespace ARM\Appointments;

if (!defined('ABSPATH')) exit;

final class Controller
{
    public static function boot(): void
    {
        add_action('init', [__CLASS__, 'register_post_type']);
    }

    public static function register_post_type(): void
    {
        
    }

    public static function create(int $customer_id, int $estimate_id, string $start, string $end): int
    {
        global $db;
        $table = $db->prefix . 'arm_appointments';

        $db->insert($table, [
            'customer_id'    => $customer_id ?: null,
            'estimate_id'    => $estimate_id ?: null,
            'start_datetime' => $start,
            'end_datetime'   => $end,
            'status'         => 'pending',
            'created_at'     => current_time('mysql'),
        ]);

        $id = (int) $db->insert_id;
        do_action('arm/appointment/booked', $id, $estimate_id, $start, $end);

        return $id;
    }

    public static function update_times(int $id, string $start, string $end): void
    {
        global $db;
        $table = $db->prefix . 'arm_appointments';

        $db->update(
            $table,
            ['start_datetime' => $start, 'end_datetime' => $end, 'updated_at' => current_time('mysql')],
            ['id' => $id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        do_action('arm/appointment/updated', $id, $start, $end);
    }

    public static function update_status(int $id, string $status): void
    {
        global $db;
        $table = $db->prefix . 'arm_appointments';

        $db->update(
            $table,
            ['status' => $status, 'updated_at' => current_time('mysql')],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        );

        if ($status === 'cancelled') {
            $estimate_id = (int) $db->get_var($db->prepare("SELECT estimate_id FROM $table WHERE id=%d", $id));
            do_action('arm/appointment/canceled', $id, $estimate_id);
        }
    }

    public static function get_for_customer(int $customer_id)
    {
        global $db;
        $table = $db->prefix . 'arm_appointments';

        return $db->get_results($db->prepare("SELECT * FROM $table WHERE customer_id=%d ORDER BY start_datetime ASC", $customer_id));
    }
}
