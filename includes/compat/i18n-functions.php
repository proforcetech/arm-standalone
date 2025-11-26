<?php
/**
 * WordPress Internationalization (i18n) Functions Compatibility Layer
 * Implements __, _e, _x, _ex, _n, esc_attr__, esc_html__, etc.
 */

declare(strict_types=1);

if (!function_exists('__')) {
    /**
     * Retrieve the translation of $text.
     */
    function __(string $text, string $domain = 'default'): string
    {
        return translate($text, $domain);
    }
}

if (!function_exists('_e')) {
    /**
     * Display translated text.
     */
    function _e(string $text, string $domain = 'default'): void
    {
        echo translate($text, $domain);
    }
}

if (!function_exists('_x')) {
    /**
     * Retrieve translated string with context.
     */
    function _x(string $text, string $context, string $domain = 'default'): string
    {
        return translate_with_context($text, $context, $domain);
    }
}

if (!function_exists('_ex')) {
    /**
     * Display translated string with context.
     */
    function _ex(string $text, string $context, string $domain = 'default'): void
    {
        echo translate_with_context($text, $context, $domain);
    }
}

if (!function_exists('_n')) {
    /**
     * Translates and retrieves the singular or plural form.
     */
    function _n(string $single, string $plural, int $number, string $domain = 'default'): string
    {
        return translate_plural($single, $plural, $number, $domain);
    }
}

if (!function_exists('_nx')) {
    /**
     * Translates and retrieves the singular or plural form with context.
     */
    function _nx(string $single, string $plural, int $number, string $context, string $domain = 'default'): string
    {
        return translate_plural_with_context($single, $plural, $number, $context, $domain);
    }
}

if (!function_exists('esc_attr__')) {
    /**
     * Retrieve the translation of $text and escapes it for safe use in an attribute.
     */
    function esc_attr__(string $text, string $domain = 'default'): string
    {
        return esc_attr(translate($text, $domain));
    }
}

if (!function_exists('esc_html__')) {
    /**
     * Retrieve the translation of $text and escapes it for safe use in HTML output.
     */
    function esc_html__(string $text, string $domain = 'default'): string
    {
        return esc_html(translate($text, $domain));
    }
}

if (!function_exists('esc_attr_e')) {
    /**
     * Display translated text that has been escaped for safe use in an attribute.
     */
    function esc_attr_e(string $text, string $domain = 'default'): void
    {
        echo esc_attr(translate($text, $domain));
    }
}

if (!function_exists('esc_html_e')) {
    /**
     * Display translated text that has been escaped for safe use in HTML output.
     */
    function esc_html_e(string $text, string $domain = 'default'): void
    {
        echo esc_html(translate($text, $domain));
    }
}

if (!function_exists('esc_attr_x')) {
    /**
     * Translate string with context and escape for attributes.
     */
    function esc_attr_x(string $text, string $context, string $domain = 'default'): string
    {
        return esc_attr(translate_with_context($text, $context, $domain));
    }
}

if (!function_exists('esc_html_x')) {
    /**
     * Translate string with context and escape for HTML.
     */
    function esc_html_x(string $text, string $context, string $domain = 'default'): string
    {
        return esc_html(translate_with_context($text, $context, $domain));
    }
}

if (!function_exists('translate')) {
    /**
     * Core translation function - simple passthrough for now
     * Can be extended to load translation files
     */
    function translate(string $text, string $domain = 'default'): string
    {
        // Apply filters to allow translation plugins
        $translation = apply_filters('gettext', $text, $text, $domain);
        return $translation;
    }
}

if (!function_exists('translate_with_context')) {
    /**
     * Translate with context
     */
    function translate_with_context(string $text, string $context, string $domain = 'default'): string
    {
        $translation = apply_filters('gettext_with_context', $text, $text, $context, $domain);
        return $translation;
    }
}

if (!function_exists('translate_plural')) {
    /**
     * Translate plural forms
     */
    function translate_plural(string $single, string $plural, int $number, string $domain = 'default'): string
    {
        $translation = $number === 1 ? $single : $plural;
        $translation = apply_filters('ngettext', $translation, $single, $plural, $number, $domain);
        return $translation;
    }
}

if (!function_exists('translate_plural_with_context')) {
    /**
     * Translate plural forms with context
     */
    function translate_plural_with_context(string $single, string $plural, int $number, string $context, string $domain = 'default'): string
    {
        $translation = $number === 1 ? $single : $plural;
        $translation = apply_filters('ngettext_with_context', $translation, $single, $plural, $number, $context, $domain);
        return $translation;
    }
}

if (!function_exists('load_textdomain')) {
    /**
     * Load a .mo file into the text domain
     * Stub for now - can be extended
     */
    function load_textdomain(string $domain, string $mofile): bool
    {
        // Future: Implement actual .mo file loading
        do_action('load_textdomain', $domain, $mofile);
        return true;
    }
}

if (!function_exists('load_plugin_textdomain')) {
    /**
     * Load the plugin's translated strings
     * Stub for now
     */
    function load_plugin_textdomain(string $domain, $deprecated = false, $plugin_rel_path = false): bool
    {
        do_action('load_plugin_textdomain', $domain, $plugin_rel_path);
        return true;
    }
}

if (!function_exists('load_theme_textdomain')) {
    /**
     * Load the theme's translated strings
     * Stub for now
     */
    function load_theme_textdomain(string $domain, $path = false): bool
    {
        do_action('load_theme_textdomain', $domain, $path);
        return true;
    }
}

if (!function_exists('get_locale')) {
    /**
     * Get the current locale
     */
    function get_locale(): string
    {
        $locale = apply_filters('locale', 'en_US');
        return $locale;
    }
}

if (!function_exists('is_rtl')) {
    /**
     * Whether the current locale is right-to-left (RTL)
     */
    function is_rtl(): bool
    {
        $rtl_locales = ['ar', 'he_IL', 'fa_IR'];
        $locale = get_locale();

        foreach ($rtl_locales as $rtl) {
            if (str_starts_with($locale, $rtl)) {
                return true;
            }
        }

        return false;
    }
}
