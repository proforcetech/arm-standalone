<?php
namespace ARM\TimeLogs;

if (!defined('ABSPATH')) exit;

final class Assets
{
    public static function boot(): void
    {
        add_action('admin_enqueue_scripts', [__CLASS__, 'register']);
        add_action('enqueue_scripts', [__CLASS__, 'register']);
    }

    public static function register(): void
    {
        if (!style_is('arm-re-admin', 'registered')) {
            register_style(
                'arm-re-admin',
                ARM_RE_URL . 'assets/css/arm-frontend.css',
                [],
                ARM_RE_VERSION
            );
        }

        register_script(
            'arm-tech-time',
            ARM_RE_URL . 'assets/js/arm-tech-time.js',
            ['jquery'],
            ARM_RE_VERSION,
            true
        );
    }
}
