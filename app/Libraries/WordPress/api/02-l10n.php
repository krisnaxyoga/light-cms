<?php

use App\Libraries\WordPress\MoFile;
use App\Libraries\WordPress\Registry;

/**
 * Translation functions. Domains registered through
 * load_theme_textdomain()/load_plugin_textdomain() are looked up in the
 * theme's language directory; anything untranslated falls through to the
 * source string, which is exactly what WordPress does for en_US.
 */

if (! function_exists('wp_locale')) {
    function wp_locale(): string
    {
        return apply_filters('locale', (string) (get_option('WPLANG', '') ?: 'en_US'));
    }
}

if (! function_exists('get_locale')) {
    function get_locale(): string
    {
        return wp_locale();
    }
}

if (! function_exists('determine_locale')) {
    function determine_locale(): string
    {
        return wp_locale();
    }
}

if (! function_exists('wp_translate')) {
    /** @internal shared lookup used by __() and friends */
    function wp_translate(string $text, string $domain = 'default', string $context = ''): string
    {
        static $loaded = [];

        if (! isset($loaded[$domain])) {
            $path = Registry::$textDomains[$domain] ?? null;
            $loaded[$domain] = $path !== null ? MoFile::load($path) : false;
        }

        $mo = $loaded[$domain];

        if ($mo instanceof MoFile) {
            $translated = $mo->translate($text, $context);

            if ($translated !== null && $translated !== '') {
                return $translated;
            }
        }

        return $text;
    }
}

if (! function_exists('__')) {
    function __(string $text, string $domain = 'default'): string
    {
        return apply_filters('gettext', wp_translate($text, $domain), $text, $domain);
    }
}

if (! function_exists('_e')) {
    function _e(string $text, string $domain = 'default'): void
    {
        echo __($text, $domain);
    }
}

if (! function_exists('_x')) {
    function _x(string $text, string $context, string $domain = 'default'): string
    {
        return apply_filters('gettext_with_context', wp_translate($text, $domain, $context), $text, $context, $domain);
    }
}

if (! function_exists('_ex')) {
    function _ex(string $text, string $context, string $domain = 'default'): void
    {
        echo _x($text, $context, $domain);
    }
}

if (! function_exists('_n')) {
    function _n(string $single, string $plural, int $number, string $domain = 'default'): string
    {
        static $loaded = [];

        if (! isset($loaded[$domain])) {
            $path = Registry::$textDomains[$domain] ?? null;
            $loaded[$domain] = $path !== null ? MoFile::load($path) : false;
        }

        if ($loaded[$domain] instanceof MoFile) {
            $translated = $loaded[$domain]->translatePlural($single, $plural, $number);

            if ($translated !== null && $translated !== '') {
                return $translated;
            }
        }

        return $number === 1 ? $single : $plural;
    }
}

if (! function_exists('_nx')) {
    function _nx(string $single, string $plural, int $number, string $context, string $domain = 'default'): string
    {
        return _n($single, $plural, $number, $domain);
    }
}

if (! function_exists('_n_noop')) {
    function _n_noop(string $singular, string $plural, ?string $domain = null): array
    {
        return ['0' => $singular, '1' => $plural, 'singular' => $singular, 'plural' => $plural, 'context' => null, 'domain' => $domain];
    }
}

if (! function_exists('_nx_noop')) {
    function _nx_noop(string $singular, string $plural, string $context, ?string $domain = null): array
    {
        return ['0' => $singular, '1' => $plural, 'singular' => $singular, 'plural' => $plural, 'context' => $context, 'domain' => $domain];
    }
}

if (! function_exists('translate_nooped_plural')) {
    function translate_nooped_plural(array $nooped_plural, int $count, string $domain = 'default'): string
    {
        return _n($nooped_plural['singular'], $nooped_plural['plural'], $count, $nooped_plural['domain'] ?? $domain);
    }
}

if (! function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = 'default'): string
    {
        return esc_html(__($text, $domain));
    }
}

if (! function_exists('esc_html_e')) {
    function esc_html_e(string $text, string $domain = 'default'): void
    {
        echo esc_html(__($text, $domain));
    }
}

if (! function_exists('esc_html_x')) {
    function esc_html_x(string $text, string $context, string $domain = 'default'): string
    {
        return esc_html(_x($text, $context, $domain));
    }
}

if (! function_exists('esc_attr__')) {
    function esc_attr__(string $text, string $domain = 'default'): string
    {
        return esc_attr(__($text, $domain));
    }
}

if (! function_exists('esc_attr_e')) {
    function esc_attr_e(string $text, string $domain = 'default'): void
    {
        echo esc_attr(__($text, $domain));
    }
}

if (! function_exists('esc_attr_x')) {
    function esc_attr_x(string $text, string $context, string $domain = 'default'): string
    {
        return esc_attr(_x($text, $context, $domain));
    }
}

if (! function_exists('load_textdomain')) {
    function load_textdomain(string $domain, string $mofile, string $locale = ''): bool
    {
        if (! is_file($mofile)) {
            return false;
        }

        Registry::$textDomains[$domain] = $mofile;

        return true;
    }
}

if (! function_exists('load_theme_textdomain')) {
    function load_theme_textdomain(string $domain, string $path = ''): bool
    {
        $locale = determine_locale();
        $path   = $path !== '' ? rtrim($path, '/') : get_template_directory() . '/languages';

        foreach ([$path . '/' . $locale . '.mo', $path . '/' . $domain . '-' . $locale . '.mo'] as $candidate) {
            if (is_file($candidate)) {
                return load_textdomain($domain, $candidate, $locale);
            }
        }

        return false;
    }
}

if (! function_exists('load_child_theme_textdomain')) {
    function load_child_theme_textdomain(string $domain, string $path = ''): bool
    {
        return load_theme_textdomain($domain, $path !== '' ? $path : get_stylesheet_directory() . '/languages');
    }
}

if (! function_exists('load_plugin_textdomain')) {
    function load_plugin_textdomain(string $domain, $deprecated = false, string $plugin_rel_path = ''): bool
    {
        $locale = determine_locale();
        $path   = rtrim(WP_PLUGIN_DIR . '/' . trim($plugin_rel_path, '/'), '/');

        foreach ([$path . '/' . $domain . '-' . $locale . '.mo', $path . '/' . $locale . '.mo'] as $candidate) {
            if (is_file($candidate)) {
                return load_textdomain($domain, $candidate, $locale);
            }
        }

        return false;
    }
}

if (! function_exists('unload_textdomain')) {
    function unload_textdomain(string $domain): bool
    {
        unset(Registry::$textDomains[$domain]);

        return true;
    }
}

if (! function_exists('is_textdomain_loaded')) {
    function is_textdomain_loaded(string $domain): bool
    {
        return isset(Registry::$textDomains[$domain]);
    }
}
