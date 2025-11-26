<?php
/**
 * WordPress Sanitization and Escaping Functions Compatibility Layer
 * Critical for security - escapes output and sanitizes input
 */

declare(strict_types=1);

if (!function_exists('esc_html')) {
    /**
     * Escape HTML entities
     */
    function esc_html(string $text): string
    {
        $safe_text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return apply_filters('esc_html', $safe_text, $text);
    }
}

if (!function_exists('esc_attr')) {
    /**
     * Escape HTML attributes
     */
    function esc_attr(string $text): string
    {
        $safe_text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return apply_filters('esc_attr', $safe_text, $text);
    }
}

if (!function_exists('esc_url')) {
    /**
     * Sanitize URL for safe output
     */
    function esc_url(string $url, ?array $protocols = null, string $_context = 'display'): string
    {
        if (empty($url)) {
            return $url;
        }

        $url = str_replace(' ', '%20', ltrim($url));
        $url = preg_replace('|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\[\]\\x80-\\xff]|i', '', $url);

        if (str_contains($url, ':')) {
            $scheme = parse_url($url, PHP_URL_SCHEME);
            if ($scheme !== false && $scheme !== null) {
                if ($protocols === null) {
                    $protocols = ['http', 'https', 'ftp', 'ftps', 'mailto', 'news', 'irc', 'tel'];
                }

                if (!in_array(strtolower($scheme), $protocols, true)) {
                    return '';
                }
            }
        }

        $url = str_replace('&amp;', '&#038;', $url);
        $url = str_replace("'", '&#039;', $url);

        return apply_filters('clean_url', $url, $url, $_context);
    }
}

if (!function_exists('esc_url_raw')) {
    /**
     * Sanitize URL for database storage
     */
    function esc_url_raw(string $url, ?array $protocols = null): string
    {
        return esc_url($url, $protocols, 'db');
    }
}

if (!function_exists('esc_js')) {
    /**
     * Escape for JavaScript
     */
    function esc_js(string $text): string
    {
        $safe_text = json_encode($text, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $safe_text = trim($safe_text, '"');
        return apply_filters('esc_js', $safe_text, $text);
    }
}

if (!function_exists('esc_textarea')) {
    /**
     * Escape for textarea values
     */
    function esc_textarea(string $text): string
    {
        $safe_text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return apply_filters('esc_textarea', $safe_text, $text);
    }
}

if (!function_exists('esc_sql')) {
    /**
     * Escape for SQL LIKE special characters
     */
    function esc_sql($data)
    {
        global $db;

        if (is_array($data)) {
            foreach ($data as &$value) {
                $value = esc_sql($value);
            }
            return $data;
        }

        return $db->esc_like((string) $data);
    }
}

if (!function_exists('sanitize_text_field')) {
    /**
     * Sanitize a string from user input
     */
    function sanitize_text_field(string $str): string
    {
        $filtered = strip_tags($str);
        $filtered = trim($filtered);
        $filtered = stripslashes($filtered);

        $found = false;
        while (preg_match('/%[a-f0-9]{2}/i', $filtered, $match)) {
            $filtered = str_replace($match[0], '', $filtered);
            $found = true;
        }

        if ($found) {
            $filtered = trim(preg_replace('/ +/', ' ', $filtered));
        }

        return apply_filters('sanitize_text_field', $filtered, $str);
    }
}

if (!function_exists('sanitize_email')) {
    /**
     * Sanitize email address
     */
    function sanitize_email(string $email): string
    {
        $email = preg_replace('/[^a-zA-Z0-9@._\-]/', '', $email);
        return apply_filters('sanitize_email', $email);
    }
}

if (!function_exists('sanitize_key')) {
    /**
     * Sanitize a string key
     */
    function sanitize_key(string $key): string
    {
        $key = strtolower($key);
        $key = preg_replace('/[^a-z0-9_\-]/', '', $key);
        return apply_filters('sanitize_key', $key);
    }
}

if (!function_exists('sanitize_title')) {
    /**
     * Sanitize a title string
     */
    function sanitize_title(string $title, string $fallback = '', string $context = 'save'): string
    {
        $title = strip_tags($title);
        $title = apply_filters('sanitize_title', $title, $fallback, $context);

        if ('' === $title && '' !== $fallback) {
            $title = $fallback;
        }

        return $title;
    }
}

if (!function_exists('sanitize_file_name')) {
    /**
     * Sanitize a filename
     */
    function sanitize_file_name(string $filename): string
    {
        $filename = preg_replace('/[^a-zA-Z0-9._\-]/', '', $filename);
        $filename = preg_replace('/\.{2,}/', '.', $filename);
        return apply_filters('sanitize_file_name', $filename);
    }
}

if (!function_exists('kses_post')) {
    /**
     * Sanitize content for allowed HTML tags for post content
     */
    function kses_post($data): string
    {
        $allowed_html = [
            'a' => ['href' => [], 'title' => [], 'target' => [], 'rel' => []],
            'br' => [],
            'em' => [],
            'strong' => [],
            'b' => [],
            'i' => [],
            'u' => [],
            'p' => ['class' => [], 'id' => []],
            'div' => ['class' => [], 'id' => []],
            'span' => ['class' => [], 'id' => []],
            'ul' => ['class' => []],
            'ol' => ['class' => []],
            'li' => [],
            'h1' => ['class' => [], 'id' => []],
            'h2' => ['class' => [], 'id' => []],
            'h3' => ['class' => [], 'id' => []],
            'h4' => ['class' => [], 'id' => []],
            'h5' => ['class' => [], 'id' => []],
            'h6' => ['class' => [], 'id' => []],
            'img' => ['src' => [], 'alt' => [], 'class' => [], 'width' => [], 'height' => []],
            'table' => ['class' => []],
            'thead' => [],
            'tbody' => [],
            'tr' => [],
            'th' => [],
            'td' => [],
            'blockquote' => ['class' => []],
            'code' => ['class' => []],
            'pre' => ['class' => []],
        ];

        return kses($data, $allowed_html);
    }
}

if (!function_exists('kses')) {
    /**
     * Strip disallowed HTML tags and attributes
     */
    function kses($string, $allowed_html, $allowed_protocols = []): string
    {
        if (is_array($string)) {
            return array_map(function($item) use ($allowed_html, $allowed_protocols) {
                return kses($item, $allowed_html, $allowed_protocols);
            }, $string);
        }

        $string = (string) $string;

        if (empty($allowed_html)) {
            return strip_tags($string);
        }

        // Build allowed tags string for strip_tags
        $allowed_tags = '';
        foreach (array_keys($allowed_html) as $tag) {
            $allowed_tags .= "<$tag>";
        }

        $string = strip_tags($string, $allowed_tags);

        // Remove disallowed attributes (simplified version)
        foreach ($allowed_html as $tag => $attrs) {
            if (empty($attrs)) {
                continue;
            }

            $pattern = "/<$tag\s+([^>]*)>/i";
            $string = preg_replace_callback($pattern, function($matches) use ($tag, $attrs) {
                $attributes = $matches[1];
                $cleaned_attrs = [];

                // Parse attributes
                preg_match_all('/([a-z\-]+)\s*=\s*["\']([^"\']*)["\']|([a-z\-]+)\s*=\s*([^\s>]+)/i', $attributes, $attr_matches, PREG_SET_ORDER);

                foreach ($attr_matches as $attr) {
                    $attr_name = strtolower($attr[1] ?: $attr[3]);
                    $attr_value = $attr[2] ?: $attr[4];

                    if (isset($attrs[$attr_name]) || in_array($attr_name, $attrs, true)) {
                        $cleaned_attrs[] = $attr_name . '="' . esc_attr($attr_value) . '"';
                    }
                }

                $cleaned = '<' . $tag;
                if (!empty($cleaned_attrs)) {
                    $cleaned .= ' ' . implode(' ', $cleaned_attrs);
                }
                $cleaned .= '>';

                return $cleaned;
            }, $string);
        }

        return $string;
    }
}

if (!function_exists('kses_data')) {
    /**
     * Sanitize content with very limited HTML
     */
    function kses_data(string $data): string
    {
        $allowed_html = [
            'strong' => [],
            'em' => [],
            'b' => [],
            'i' => [],
            'code' => [],
            'a' => ['href' => [], 'title' => []],
        ];

        return kses($data, $allowed_html);
    }
}

if (!function_exists('strip_all_tags')) {
    /**
     * Strip all HTML tags including script and style
     */
    function strip_all_tags(string $string, bool $remove_breaks = false): string
    {
        $string = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $string);
        $string = strip_tags($string);

        if ($remove_breaks) {
            $string = preg_replace('/[\r\n\t ]+/', ' ', $string);
        }

        return trim($string);
    }
}

if (!function_exists('sanitize_html_class')) {
    /**
     * Sanitize HTML class name
     */
    function sanitize_html_class(string $class, string $fallback = ''): string
    {
        $class = preg_replace('/[^A-Za-z0-9_\-]/', '', $class);

        if (empty($class)) {
            $class = $fallback;
        }

        return apply_filters('sanitize_html_class', $class, $class, $fallback);
    }
}

if (!function_exists('absint')) {
    /**
     * Convert to non-negative integer
     */
    function absint($maybeint): int
    {
        return abs((int) $maybeint);
    }
}

if (!function_exists('intval')) {
    /**
     * Alias for PHP's intval - included for consistency
     */
}

if (!function_exists('unslash')) {
    /**
     * Remove slashes from string or array
     */
    function unslash($value)
    {
        return stripslashes_deep($value);
    }
}

if (!function_exists('stripslashes_deep')) {
    /**
     * Recursively strip slashes
     */
    function stripslashes_deep($value)
    {
        if (is_array($value)) {
            return array_map('stripslashes_deep', $value);
        }

        if (is_object($value)) {
            $vars = get_object_vars($value);
            foreach ($vars as $key => $data) {
                $value->{$key} = stripslashes_deep($data);
            }
            return $value;
        }

        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (!function_exists('slash')) {
    /**
     * Add slashes to string or array
     */
    function slash($value)
    {
        if (is_array($value)) {
            return array_map('slash', $value);
        }

        return is_string($value) ? addslashes($value) : $value;
    }
}
