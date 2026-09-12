<?php

use App\Libraries\WordPress\Registry;

/**
 * The Shortcode API. do_shortcode() is registered on `the_content` by the
 * frontend controller, so shortcodes in post content are expanded the way
 * they are in WordPress.
 */

if (! function_exists('add_shortcode')) {
    function add_shortcode(string $tag, callable $callback): void
    {
        Registry::$shortcodes[$tag] = $callback;
        $GLOBALS['shortcode_tags'][$tag] = $callback;
    }
}

if (! function_exists('remove_shortcode')) {
    function remove_shortcode(string $tag): void
    {
        unset(Registry::$shortcodes[$tag], $GLOBALS['shortcode_tags'][$tag]);
    }
}

if (! function_exists('remove_all_shortcodes')) {
    function remove_all_shortcodes(): void
    {
        Registry::$shortcodes = [];
        $GLOBALS['shortcode_tags'] = [];
    }
}

if (! function_exists('shortcode_exists')) {
    function shortcode_exists(string $tag): bool
    {
        return isset(Registry::$shortcodes[$tag]);
    }
}

if (! function_exists('has_shortcode')) {
    function has_shortcode(string $content, string $tag): bool
    {
        return $content !== '' && shortcode_exists($tag) && str_contains($content, '[' . $tag);
    }
}

if (! function_exists('get_shortcode_regex')) {
    function get_shortcode_regex(?array $tagnames = null): string
    {
        $tagnames ??= array_keys(Registry::$shortcodes);
        $tagregexp = implode('|', array_map('preg_quote', $tagnames));

        return '\\[(\\[?)(' . $tagregexp . ')(?![\\w-])([^\\]\\/]*(?:\\/(?!\\])[^\\]\\/]*)*?)(?:(\\/)\\]|\\](?:([^\\[]*+(?:\\[(?!\\/\\2\\])[^\\[]*+)*+)\\[\\/\\2\\])?)(\\]?)';
    }
}

if (! function_exists('do_shortcode')) {
    function do_shortcode(string $content, bool $ignore_html = false): string
    {
        if (Registry::$shortcodes === [] || ! str_contains($content, '[')) {
            return $content;
        }

        $pattern = get_shortcode_regex();

        // PREG_UNMATCHED_AS_NULL keeps the difference between a
        // self-closing shortcode (content null) and an enclosing one with
        // an empty body (content ''), which handlers do act on.
        return preg_replace_callback('/' . $pattern . '/s', static function (array $match): string {
            // Escaped shortcode: [[tag]] renders literally.
            if ($match[1] === '[' && $match[6] === ']') {
                return substr($match[0], 1, -1);
            }

            $tag      = $match[2];
            $attrs    = shortcode_parse_atts((string) $match[3]);
            $callback = Registry::$shortcodes[$tag];
            $content  = $match[5] ?? null;

            return (string) $callback($attrs, $content, $tag);
        }, $content, -1, $count, PREG_UNMATCHED_AS_NULL) ?? $content;
    }
}

if (! function_exists('shortcode_parse_atts')) {
    function shortcode_parse_atts(string $text)
    {
        $atts    = [];
        $pattern = '/([\w-]+)\s*=\s*"([^"]*)"(?:\s|$)|([\w-]+)\s*=\s*\'([^\']*)\'(?:\s|$)|([\w-]+)\s*=\s*([^\s\'"]+)(?:\s|$)|"([^"]*)"(?:\s|$)|\'([^\']*)\'(?:\s|$)|(\S+)(?:\s|$)/';
        $text    = preg_replace("/[\x{00a0}\x{200b}]+/u", ' ', $text) ?? $text;

        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER) === 0) {
            return ltrim($text) === '' ? [] : [ltrim($text)];
        }

        foreach ($matches as $match) {
            if (($match[1] ?? '') !== '') {
                $atts[strtolower($match[1])] = stripcslashes($match[2]);
            } elseif (($match[3] ?? '') !== '') {
                $atts[strtolower($match[3])] = stripcslashes($match[4]);
            } elseif (($match[5] ?? '') !== '') {
                $atts[strtolower($match[5])] = stripcslashes($match[6]);
            } elseif (($match[7] ?? '') !== '') {
                $atts[] = stripcslashes($match[7]);
            } elseif (isset($match[8]) && $match[8] !== '') {
                $atts[] = stripcslashes($match[8]);
            } elseif (isset($match[9])) {
                $atts[] = stripcslashes($match[9]);
            }
        }

        return $atts;
    }
}

if (! function_exists('shortcode_atts')) {
    function shortcode_atts(array $pairs, $atts, string $shortcode = ''): array
    {
        $atts = (array) $atts;
        $out  = [];

        foreach ($pairs as $name => $default) {
            $out[$name] = array_key_exists($name, $atts) ? $atts[$name] : $default;
        }

        return $shortcode !== '' ? apply_filters('shortcode_atts_' . $shortcode, $out, $pairs, $atts, $shortcode) : $out;
    }
}

if (! function_exists('strip_shortcodes')) {
    function strip_shortcodes(string $content): string
    {
        if (Registry::$shortcodes === []) {
            return $content;
        }

        return preg_replace('/' . get_shortcode_regex() . '/s', '', $content) ?? $content;
    }
}

if (! function_exists('shortcode_unautop')) {
    function shortcode_unautop(string $content): string
    {
        if (Registry::$shortcodes === []) {
            return $content;
        }

        return preg_replace('#<p>\s*(' . get_shortcode_regex() . ')\s*</p>#s', '$1', $content) ?? $content;
    }
}
