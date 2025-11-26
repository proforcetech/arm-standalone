<?php
/**
 * WordPress Options API Compatibility Layer
 * Implements get_option, update_option, add_option, delete_option
 */

declare(strict_types=1);

if (!function_exists('get_option')) {
    function get_option(string $option, $default = false)
    {
        global $db;

        $option = trim($option);
        if (empty($option)) {
            return $default;
        }

        $value = apply_filters("pre_option_{$option}", false, $option, $default);

        if ($value !== false) {
            return $value;
        }

        $table = $db->prefix . 'options';
        $row = $db->get_row($db->prepare(
            "SELECT option_value FROM {$table} WHERE option_name = %s LIMIT 1",
            $option
        ));

        if (is_object($row)) {
            $value = $row->option_value;
        } else {
            return apply_filters("default_option_{$option}", $default, $option, false);
        }

        // Maybe unserialize
        if (is_serialized($value)) {
            $value = @unserialize($value);
        }

        return apply_filters("option_{$option}", $value, $option);
    }
}

if (!function_exists('update_option')) {
    function update_option(string $option, $value, $autoload = null): bool
    {
        global $db;

        $option = trim($option);
        if (empty($option)) {
            return false;
        }

        $old_value = get_option($option);

        $value = apply_filters("pre_update_option_{$option}", $value, $old_value, $option);

        if ($value === $old_value) {
            return false;
        }

        if (is_array($value) || is_object($value)) {
            $value = serialize($value);
        }

        $table = $db->prefix . 'options';

        $exists = $db->get_var($db->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE option_name = %s",
            $option
        ));

        if ($exists) {
            $result = $db->update(
                $table,
                ['option_value' => $value],
                ['option_name' => $option]
            );
        } else {
            $autoload = $autoload ?? 'yes';
            $result = $db->insert(
                $table,
                [
                    'option_name' => $option,
                    'option_value' => $value,
                    'autoload' => $autoload
                ]
            );
        }

        if ($result) {
            do_action("update_option_{$option}", $old_value, $value, $option);
            do_action('update_option', $option, $old_value, $value);
        }

        return $result !== false;
    }
}

if (!function_exists('add_option')) {
    function add_option(string $option, $value = '', string $deprecated = '', $autoload = 'yes'): bool
    {
        global $db;

        $option = trim($option);
        if (empty($option)) {
            return false;
        }

        $value = apply_filters("pre_add_option_{$option}", $value, $option);

        if (is_array($value) || is_object($value)) {
            $value = serialize($value);
        }

        $table = $db->prefix . 'options';

        $exists = $db->get_var($db->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE option_name = %s",
            $option
        ));

        if ($exists) {
            return false;
        }

        $result = $db->insert(
            $table,
            [
                'option_name' => $option,
                'option_value' => $value,
                'autoload' => $autoload
            ]
        );

        if ($result) {
            do_action("add_option_{$option}", $option, $value);
            do_action('add_option', $option, $value);
        }

        return $result !== false;
    }
}

if (!function_exists('delete_option')) {
    function delete_option(string $option): bool
    {
        global $db;

        $option = trim($option);
        if (empty($option)) {
            return false;
        }

        $value = get_option($option);

        do_action("delete_option_{$option}", $option);

        $table = $db->prefix . 'options';
        $result = $db->delete($table, ['option_name' => $option]);

        if ($result) {
            do_action('delete_option', $option);
        }

        return $result !== false;
    }
}

if (!function_exists('get_site_option')) {
    function get_site_option(string $option, $default = false, bool $deprecated = true)
    {
        return get_option($option, $default);
    }
}

if (!function_exists('update_site_option')) {
    function update_site_option(string $option, $value): bool
    {
        return update_option($option, $value);
    }
}

if (!function_exists('delete_site_option')) {
    function delete_site_option(string $option): bool
    {
        return delete_option($option);
    }
}

if (!function_exists('is_serialized')) {
    function is_serialized($data, bool $strict = true): bool
    {
        if (!is_string($data)) {
            return false;
        }

        $data = trim($data);
        if ('N;' === $data) {
            return true;
        }
        if (strlen($data) < 4) {
            return false;
        }
        if (':' !== $data[1]) {
            return false;
        }
        if ($strict) {
            $lastc = substr($data, -1);
            if (';' !== $lastc && '}' !== $lastc) {
                return false;
            }
        } else {
            $semicolon = strpos($data, ';');
            $brace     = strpos($data, '}');
            if (false === $semicolon && false === $brace) {
                return false;
            }
            if (false !== $semicolon && $semicolon < 3) {
                return false;
            }
            if (false !== $brace && $brace < 4) {
                return false;
            }
        }
        $token = $data[0];
        switch ($token) {
            case 's':
                if ($strict) {
                    if ('"' !== substr($data, -2, 1)) {
                        return false;
                    }
                } elseif (false === strpos($data, '"')) {
                    return false;
                }
                // Fall through.
            case 'a':
            case 'O':
            case 'C':
                return (bool) preg_match("/^{$token}:[0-9]+:/s", $data);
            case 'b':
            case 'i':
            case 'd':
                $end = $strict ? '$' : '';
                return (bool) preg_match("/^{$token}:[0-9.E+-]+;$end/", $data);
        }
        return false;
    }
}

if (!function_exists('maybe_serialize')) {
    function maybe_serialize($data)
    {
        if (is_array($data) || is_object($data)) {
            return serialize($data);
        }

        if (is_serialized($data, false)) {
            return serialize($data);
        }

        return $data;
    }
}

if (!function_exists('maybe_unserialize')) {
    function maybe_unserialize($data)
    {
        if (is_serialized($data)) {
            return @unserialize(trim($data));
        }

        return $data;
    }
}
