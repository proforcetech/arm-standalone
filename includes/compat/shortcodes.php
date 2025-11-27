<?php
/**
 * WordPress Shortcode Compatibility Layer
 */

if (!isset($shortcode_tags) || !is_array($shortcode_tags)) {
    $shortcode_tags = [];
}

if (!function_exists('add_shortcode')) {
    function add_shortcode(string $tag, callable $func): void
    {
        global $shortcode_tags;

        if ($tag === '') {
            return;
        }

        $shortcode_tags[$tag] = $func;
    }
}

if (!function_exists('shortcode_exists')) {
    function shortcode_exists(string $tag): bool
    {
        global $shortcode_tags;

        return isset($shortcode_tags[$tag]);
    }
}

if (!function_exists('remove_shortcode')) {
    function remove_shortcode(string $tag): void
    {
        global $shortcode_tags;

        unset($shortcode_tags[$tag]);
    }
}

if (!function_exists('remove_all_shortcodes')) {
    function remove_all_shortcodes(): void
    {
        global $shortcode_tags;

        $shortcode_tags = [];
    }
}

if (!function_exists('shortcode_atts')) {
    function shortcode_atts(array $pairs, $atts, string $shortcode = ''): array
    {
        $atts = (array) $atts;
        $out  = $pairs;

        foreach ($atts as $name => $value) {
            if (array_key_exists($name, $pairs)) {
                $out[$name] = $value;
            }
        }

        return $out;
    }
}

if (!function_exists('shortcode_parse_atts')) {
    function shortcode_parse_atts($text): array
    {
        $text = is_string($text) ? trim($text) : '';
        if ($text === '') {
            return [];
        }

        $atts = [];
        preg_match_all('/(\w+)\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s"\']+))/', $text, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $value = $match[3] !== '' ? $match[3] : ($match[4] !== '' ? $match[4] : $match[5]);
            $atts[$match[1]] = stripcslashes($value);
        }

        return $atts;
    }
}

if (!function_exists('do_shortcode')) {
    function do_shortcode($content): string
    {
        global $shortcode_tags;

        $content = (string) $content;

        if ($content === '' || empty($shortcode_tags)) {
            return $content;
        }

        $pattern = '/\[(\/)?(\w+)([^\]]*)\]/';

        return (string) preg_replace_callback($pattern, static function (array $matches) use ($shortcode_tags) {
            $isClosing = $matches[1] === '/';
            $tag       = $matches[2];

            if (!isset($shortcode_tags[$tag])) {
                return $matches[0];
            }

            if ($isClosing) {
                return '';
            }

            $atts     = shortcode_parse_atts($matches[3]);
            $callback = $shortcode_tags[$tag];

            return (string) call_user_func($callback, $atts, '', $tag);
        }, $content);
    }
}
