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

if (!function_exists('has_shortcode')) {
    function has_shortcode($content, string $tag): bool
    {
        if (!shortcode_exists($tag)) {
            return false;
        }

        $pattern = get_shortcode_regex([$tag]);

        return (bool) preg_match('/' . $pattern . '/s', (string) $content);
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
        $out  = [];

        foreach ($pairs as $name => $default) {
            if (array_key_exists($name, $atts)) {
                $out[$name] = $atts[$name];
            } else {
                $out[$name] = $default;
            }
        }

        // Preserve any extra attributes when a shortcode name is provided for parity with WordPress
        if ($shortcode !== '') {
            foreach ($atts as $name => $value) {
                if (!array_key_exists($name, $out)) {
                    $out[$name] = $value;
                }
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
        $pattern = '/(\w+)\s*=\s*"([^"]*)"|(\w+)\s*=\s*\'([^\']*)\'|(\w+)\s*=\s*([^\s\'\"]+)|"([^"]*)"|\'([^\']*)\'|(\S+)/';

        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                if (!empty($match[1])) {
                    $atts[strtolower($match[1])] = stripcslashes($match[2]);
                } elseif (!empty($match[3])) {
                    $atts[strtolower($match[3])] = stripcslashes($match[4]);
                } elseif (!empty($match[5])) {
                    $atts[strtolower($match[5])] = stripcslashes($match[6]);
                } elseif (isset($match[7]) && $match[7] !== '') {
                    $atts[] = stripcslashes($match[7]);
                } elseif (isset($match[8]) && $match[8] !== '') {
                    $atts[] = stripcslashes($match[8]);
                } elseif (isset($match[9])) {
                    $atts[] = stripcslashes($match[9]);
                }
            }
        }

        return $atts;
    }
}

if (!function_exists('get_shortcode_regex')) {
    function get_shortcode_regex(?array $tagnames = null): string
    {
        global $shortcode_tags;

        if (empty($tagnames)) {
            $tagnames = array_keys($shortcode_tags);
        }

        $tagregexp = implode('|', array_map('preg_quote', $tagnames));

        // From WordPress core: allows escaping [[tag]] and supports enclosing shortcodes.
        return '\\[(\\[?)(' . $tagregexp . ')(?![\\w-])' // Opening bracket and shortcode name.
            . '([^\\]\\/]*(?:\\/(?!\\])[^\\]\\/]*?)?)' // Attributes.
            . '(?:' // Self closing ...
            . '(\\/)' // ... with a closing slash.
            . '\\]' // Close bracket.
            . '|' // ...or...
            . '\\]' // Closing bracket.
            . '(?:' // Enclosed content.
            . '((?>[^\\[]+|\\[(?!\\/\\2\\])|(?R))*)' // Content, recursively handling nested shortcodes.
            . '\\[\\/\\2\\]' // Closing shortcode tag.
            . ')?' // End of content.
            . ')' // End of group.
            . '(\\]?)';
    }
}

if (!function_exists('do_shortcode_tag')) {
    function do_shortcode_tag(array $m): string
    {
        global $shortcode_tags;

        $tag = $m[2];

        if (!isset($shortcode_tags[$tag])) {
            return $m[0];
        }

        // Escaped shortcode like [[tag]] should output [tag]
        if ($m[1] === '[' && $m[6] === ']') {
            return substr($m[0], 1, -1);
        }

        $attr = shortcode_parse_atts($m[3]);
        $callback = $shortcode_tags[$tag];

        if ($m[4] === '/') {
            return $m[1] . call_user_func($callback, $attr, null, $tag) . $m[6];
        }

        return $m[1] . call_user_func($callback, $attr, $m[5] ?? null, $tag) . $m[6];
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

        $pattern = get_shortcode_regex();

        return (string) preg_replace_callback('/' . $pattern . '/s', 'do_shortcode_tag', $content);
    }
}

if (!function_exists('apply_shortcodes')) {
    function apply_shortcodes($content): string
    {
        return do_shortcode($content);
    }
}
