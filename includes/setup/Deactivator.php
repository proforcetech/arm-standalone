<?php

namespace ARM\Setup;

if (!defined('ABSPATH')) exit;

/**
 * Why: undo runtime wiring on deactivate (keep data).
 */
final class Deactivator
{
    public static function deactivate(): void
    {
        
        $ts = next_scheduled('arm_re_cleanup');
        if ($ts) unschedule_event($ts, 'arm_re_cleanup');
        flush_rewrite_rules();
    }
}
