<?php
namespace ARM\Appointments;

if (!defined('ABSPATH')) exit;

final class Ajax
{
    public static function boot(): void
    {
        add_action('ajax_arm_get_slots', [__CLASS__, 'get_slots']);
        add_action('ajax_nopriv_arm_get_slots', [__CLASS__, 'get_slots']);
    }

    public static function get_slots(): void
    {
        check_ajax_referer('arm_re_nonce', 'nonce');
        global $db;

        $table_avail = $db->prefix . 'arm_availability';
        $table_appt  = $db->prefix . 'arm_appointments';

        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            send_json_error(['message' => __('Invalid date supplied.', 'arm-repair-estimates')]);
        }

        $dow = (int) date('w', strtotime($date));

        $holiday = $db->get_row($db->prepare("SELECT * FROM $table_avail WHERE type='holiday' AND date=%s", $date));
        if ($holiday) {
            send_json_success([
                'slots'   => [],
                'holiday' => true,
                'label'   => $holiday->label,
            ]);
        }

        $hours = $db->get_row($db->prepare("SELECT * FROM $table_avail WHERE type='hours' AND day_of_week=%d", $dow));
        if (!$hours) {
            send_json_success(['slots' => []]);
        }

        $start = strtotime("$date {$hours->start_time}");
        $end   = strtotime("$date {$hours->end_time}");
        $slot_length = HOUR_IN_SECONDS;

        $slots = [];
        for ($t = $start; $t + $slot_length <= $end; $t += $slot_length) {
            $slot_time = date('H:i', $t);
            $exists = $db->get_var($db->prepare(
                "SELECT COUNT(*) FROM $table_appt WHERE DATE(start_datetime)=%s AND TIME(start_datetime)=%s AND status NOT IN ('cancelled')",
                $date,
                $slot_time
            ));
            if (!$exists) {
                $slots[] = $slot_time;
            }
        }

        send_json_success(['slots' => $slots]);
    }
}
