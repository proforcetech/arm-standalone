<?php
/**
 * WordPress Admin Functions Compatibility Layer
 * Implements admin menus, URLs, and utility functions
 */

declare(strict_types=1);

// Global storage for admin menus
global $_arm_admin_menu, $_arm_submenu, $_arm_registered_pages;
$_arm_admin_menu = [];
$_arm_submenu = [];
$_arm_registered_pages = [];

if (!function_exists('add_menu_page')) {
    /**
     * Add a top-level menu page
     */
    function add_menu_page(
        string $page_title,
        string $menu_title,
        string $capability,
        string $menu_slug,
        callable $callback = null,
        string $icon_url = '',
        ?int $position = null
    ): string {
        global $_arm_admin_menu, $_arm_registered_pages;

        $hookname = get_plugin_page_hookname($menu_slug, '');

        $_arm_admin_menu[$menu_slug] = [
            'page_title' => $page_title,
            'menu_title' => $menu_title,
            'capability' => $capability,
            'menu_slug' => $menu_slug,
            'callback' => $callback,
            'icon_url' => $icon_url,
            'position' => $position ?? 100,
            'hookname' => $hookname
        ];

        if ($callback && $hookname) {
            $_arm_registered_pages[$hookname] = $callback;
        }

        do_action('admin_menu', $menu_slug);

        return $hookname;
    }
}

if (!function_exists('add_submenu_page')) {
    /**
     * Add a submenu page
     */
    function add_submenu_page(
        string $parent_slug,
        string $page_title,
        string $menu_title,
        string $capability,
        string $menu_slug,
        callable $callback = null,
        ?int $position = null
    ): string {
        global $_arm_submenu, $_arm_registered_pages;

        $hookname = get_plugin_page_hookname($menu_slug, $parent_slug);

        if (!isset($_arm_submenu[$parent_slug])) {
            $_arm_submenu[$parent_slug] = [];
        }

        $_arm_submenu[$parent_slug][$menu_slug] = [
            'page_title' => $page_title,
            'menu_title' => $menu_title,
            'capability' => $capability,
            'menu_slug' => $menu_slug,
            'callback' => $callback,
            'position' => $position ?? 10,
            'hookname' => $hookname
        ];

        if ($callback && $hookname) {
            $_arm_registered_pages[$hookname] = $callback;
        }

        return $hookname;
    }
}

if (!function_exists('add_options_page')) {
    /**
     * Add submenu page to Settings menu
     */
    function add_options_page(
        string $page_title,
        string $menu_title,
        string $capability,
        string $menu_slug,
        callable $callback = null
    ): string {
        return add_submenu_page('options-general', $page_title, $menu_title, $capability, $menu_slug, $callback);
    }
}

if (!function_exists('get_plugin_page_hookname')) {
    /**
     * Get the hook name for a plugin page
     */
    function get_plugin_page_hookname(string $plugin_page, string $parent_page): string
    {
        $parent = !empty($parent_page) ? $parent_page : 'admin';
        return $parent . '_page_' . $plugin_page;
    }
}

if (!function_exists('admin_url')) {
    /**
     * Generate admin URL
     */
    function admin_url(string $path = '', string $scheme = 'admin'): string
    {
        $baseUrl = ARM_RE_URL;
        $adminPath = 'admin/';

        if (empty($path)) {
            return $baseUrl . $adminPath;
        }

        // Remove leading slash from path
        $path = ltrim($path, '/');

        return $baseUrl . $adminPath . $path;
    }
}

if (!function_exists('site_url')) {
    /**
     * Retrieve the site URL
     */
    function site_url(string $path = '', ?string $scheme = null): string
    {
        $url = ARM_RE_URL;

        if (!empty($path)) {
            $url .= '/' . ltrim($path, '/');
        }

        return $url;
    }
}

if (!function_exists('home_url')) {
    /**
     * Retrieve the home URL
     */
    function home_url(string $path = '', ?string $scheme = null): string
    {
        return site_url($path, $scheme);
    }
}

if (!function_exists('get_admin_url')) {
    /**
     * Retrieve admin URL with optional blog ID
     */
    function get_admin_url($blog_id = null, string $path = '', string $scheme = 'admin'): string
    {
        return admin_url($path, $scheme);
    }
}

if (!function_exists('current_time')) {
    /**
     * Get current time in specified format
     */
    function current_time(string $type = 'mysql', bool $gmt = false): string
    {
        $timezone = $gmt ? 'UTC' : get_option('timezone_string', 'UTC');

        if (empty($timezone)) {
            $timezone = 'UTC';
        }

        try {
            $datetime = new DateTime('now', new DateTimeZone($timezone));
        } catch (Exception $e) {
            $datetime = new DateTime('now', new DateTimeZone('UTC'));
        }

        switch ($type) {
            case 'mysql':
                return $datetime->format('Y-m-d H:i:s');
            case 'timestamp':
            case 'U':
                return (string) $datetime->getTimestamp();
            default:
                return $datetime->format($type);
        }
    }
}

if (!function_exists('current_datetime')) {
    /**
     * Get current DateTime object
     */
    function current_datetime(): DateTimeImmutable
    {
        $timezone = get_option('timezone_string', 'UTC');

        if (empty($timezone)) {
            $timezone = 'UTC';
        }

        try {
            return new DateTimeImmutable('now', new DateTimeZone($timezone));
        } catch (Exception $e) {
            return new DateTimeImmutable('now', new DateTimeZone('UTC'));
        }
    }
}

if (!function_exists('mysql2date')) {
    /**
     * Convert MySQL datetime to another format
     */
    function mysql2date(string $format, string $date, bool $translate = true): string
    {
        if (empty($date)) {
            return '';
        }

        try {
            $datetime = new DateTime($date, new DateTimeZone('UTC'));
            return $datetime->format($format);
        } catch (Exception $e) {
            return '';
        }
    }
}

if (!function_exists('human_time_diff')) {
    /**
     * Get human-readable time difference
     */
    function human_time_diff(int $from, int $to = 0): string
    {
        if (empty($to)) {
            $to = time();
        }

        $diff = abs($to - $from);

        if ($diff < MINUTE_IN_SECONDS) {
            $secs = $diff;
            return sprintf(_n('%s second', '%s seconds', $secs), $secs);
        }
        if ($diff < HOUR_IN_SECONDS) {
            $mins = round($diff / MINUTE_IN_SECONDS);
            return sprintf(_n('%s minute', '%s minutes', $mins), $mins);
        }
        if ($diff < DAY_IN_SECONDS) {
            $hours = round($diff / HOUR_IN_SECONDS);
            return sprintf(_n('%s hour', '%s hours', $hours), $hours);
        }
        if ($diff < WEEK_IN_SECONDS) {
            $days = round($diff / DAY_IN_SECONDS);
            return sprintf(_n('%s day', '%s days', $days), $days);
        }
        if ($diff < MONTH_IN_SECONDS) {
            $weeks = round($diff / WEEK_IN_SECONDS);
            return sprintf(_n('%s week', '%s weeks', $weeks), $weeks);
        }
        if ($diff < YEAR_IN_SECONDS) {
            $months = round($diff / MONTH_IN_SECONDS);
            return sprintf(_n('%s month', '%s months', $months), $months);
        }

        $years = round($diff / YEAR_IN_SECONDS);
        return sprintf(_n('%s year', '%s years', $years), $years);
    }
}

// Define time constants
if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}
if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}
if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}
if (!defined('WEEK_IN_SECONDS')) {
    define('WEEK_IN_SECONDS', 604800);
}
if (!defined('MONTH_IN_SECONDS')) {
    define('MONTH_IN_SECONDS', 2592000);
}
if (!defined('YEAR_IN_SECONDS')) {
    define('YEAR_IN_SECONDS', 31536000);
}

if (!function_exists('is_admin')) {
    /**
     * Check if current request is for admin area
     */
    function is_admin(): bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return str_contains($uri, '/admin/') || str_contains($uri, 'page=arm-');
    }
}

if (!function_exists('wp_redirect')) {
    /**
     * Redirect to another page
     */
    function wp_redirect(string $location, int $status = 302, string $x_redirect_by = 'WordPress'): bool
    {
        $location = apply_filters('wp_redirect', $location, $status);

        if (!$location) {
            return false;
        }

        $status = apply_filters('wp_redirect_status', $status, $location);

        if (!headers_sent()) {
            header("Location: $location", true, $status);
            return true;
        }

        return false;
    }
}

if (!function_exists('wp_safe_redirect')) {
    /**
     * Safely redirect to another page (same domain only)
     */
    function wp_safe_redirect(string $location, int $status = 302, string $x_redirect_by = 'WordPress'): bool
    {
        $location = wp_sanitize_redirect($location);
        return wp_redirect($location, $status, $x_redirect_by);
    }
}

if (!function_exists('wp_sanitize_redirect')) {
    /**
     * Sanitize redirect URL
     */
    function wp_sanitize_redirect(string $location): string
    {
        // Strip any script tags
        $location = preg_replace('|[^a-z0-9-~+_.?#=&;,/:%!*\[\]@]|i', '', $location);
        $location = wp_kses_no_null($location, ['slash_zero' => 'keep']);

        return $location;
    }
}

if (!function_exists('wp_kses_no_null')) {
    /**
     * Remove null characters from string
     */
    function wp_kses_no_null(string $string, array $options = []): string
    {
        if (str_contains($string, "\0")) {
            $string = str_replace("\0", '', $string);
        }

        return $string;
    }
}
