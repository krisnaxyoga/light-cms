<?php

use App\Libraries\WordPress\OptionStore;

/**
 * Escaping, sanitising, date/number formatting and the small array/string
 * helpers every theme leans on.
 */

// -- Escaping ---------------------------------------------------------------

if (! function_exists('esc_html')) {
    function esc_html($text): string
    {
        return apply_filters('esc_html', htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'), $text);
    }
}

if (! function_exists('esc_attr')) {
    function esc_attr($text): string
    {
        return apply_filters('attribute_escape', htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'), $text);
    }
}

if (! function_exists('esc_textarea')) {
    function esc_textarea($text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('esc_url')) {
    function esc_url($url, $protocols = null, string $_context = 'display'): string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return '';
        }

        $allowed = $protocols ?? ['http', 'https', 'mailto', 'tel', 'ftp', 'ftps', 'webcal', 'sms'];
        $scheme  = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        // Reject anything with a scheme we do not allow (javascript:, data:).
        if ($scheme !== '' && ! in_array($scheme, $allowed, true)) {
            return '';
        }

        $url = str_replace(['"', "'", '<', '>', ' '], ['%22', '%27', '%3C', '%3E', '%20'], $url);

        return $_context === 'display' ? htmlspecialchars($url, ENT_QUOTES, 'UTF-8') : $url;
    }
}

if (! function_exists('esc_url_raw')) {
    function esc_url_raw($url, $protocols = null): string
    {
        return esc_url($url, $protocols, 'db');
    }
}

if (! function_exists('esc_js')) {
    function esc_js($text): string
    {
        $text = str_replace(["\r\n", "\r", "\n"], '\n', (string) $text);
        $text = addcslashes($text, "'\"\\/<>");

        return $text;
    }
}

if (! function_exists('esc_xml')) {
    function esc_xml($text): string
    {
        return htmlspecialchars((string) $text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($text, bool $remove_breaks = false): string
    {
        $text = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text) ?? '';
        $text = strip_tags($text);

        if ($remove_breaks) {
            $text = preg_replace('/[\r\n\t ]+/', ' ', $text) ?? $text;
        }

        return trim($text);
    }
}

if (! function_exists('wp_kses')) {
    /**
     * A pragmatic kses: keeps the allowed tags, drops every event handler
     * attribute and any javascript:/data: URL. Not byte-identical to
     * WordPress's implementation, but it fails closed the same way.
     */
    function wp_kses($content, $allowed_html = [], $allowed_protocols = []): string
    {
        $content = (string) $content;

        $tags = is_array($allowed_html) ? array_keys($allowed_html) : [];
        $tags = $tags === [] ? [] : '<' . implode('><', $tags) . '>';

        $content = strip_tags($content, $tags ?: null);
        $content = preg_replace('/\son[a-z]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $content) ?? $content;
        $content = preg_replace('/(href|src|xlink:href|action|formaction)\s*=\s*(["\']?)\s*(?:javascript|vbscript|data)\s*:[^"\'>\s]*\2/i', '$1="#"', $content) ?? $content;

        return $content;
    }
}

if (! function_exists('wp_kses_allowed_html')) {
    function wp_kses_allowed_html(string $context = 'post'): array
    {
        $inline = ['a', 'abbr', 'b', 'strong', 'em', 'i', 'code', 'del', 'ins', 'kbd', 'mark', 'q', 's', 'small', 'span', 'sub', 'sup', 'u', 'br'];
        $block  = ['p', 'div', 'section', 'article', 'aside', 'header', 'footer', 'nav', 'main', 'figure', 'figcaption', 'blockquote', 'pre', 'hr',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'dl', 'dt', 'dd',
            'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption', 'colgroup', 'col',
            'img', 'picture', 'source', 'video', 'audio', 'iframe', 'button', 'label', 'time'];

        $tags = $context === 'strip' ? [] : ($context === 'data' ? $inline : array_merge($inline, $block));

        return array_fill_keys($tags, []);
    }
}

if (! function_exists('wp_kses_post')) {
    function wp_kses_post($data): string
    {
        return wp_kses($data, wp_kses_allowed_html('post'));
    }
}

if (! function_exists('wp_kses_data')) {
    function wp_kses_data($data): string
    {
        return wp_kses($data, wp_kses_allowed_html('data'));
    }
}

if (! function_exists('wp_filter_nohtml_kses')) {
    function wp_filter_nohtml_kses($data): string
    {
        return wp_strip_all_tags($data);
    }
}

// -- Sanitising ---------------------------------------------------------------

if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field($str): string
    {
        $str = wp_strip_all_tags((string) $str);
        $str = preg_replace('/[\r\n\t]+/', ' ', $str) ?? $str;
        $str = preg_replace('/%[a-f0-9]{2}/i', '', $str) ?? $str;

        return trim(preg_replace('/ +/', ' ', $str) ?? $str);
    }
}

if (! function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str): string
    {
        return trim(wp_strip_all_tags((string) $str));
    }
}

if (! function_exists('sanitize_title')) {
    function sanitize_title($title, string $fallback_title = '', string $context = 'save'): string
    {
        $title = sanitize_title_with_dashes((string) $title);

        return $title !== '' ? $title : $fallback_title;
    }
}

if (! function_exists('sanitize_title_with_dashes')) {
    function sanitize_title_with_dashes($title, $raw_title = '', string $context = 'display'): string
    {
        $title = strip_tags((string) $title);
        $title = preg_replace('/[^%a-zA-Z0-9 _-]/', '', $title) ?? $title;
        $title = strtolower(trim($title));
        $title = preg_replace('/[\s_]+/', '-', $title) ?? $title;

        return trim(preg_replace('/-+/', '-', $title) ?? $title, '-');
    }
}

if (! function_exists('sanitize_key')) {
    function sanitize_key($key): string
    {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key)) ?? '';
    }
}

if (! function_exists('sanitize_html_class')) {
    function sanitize_html_class($class, string $fallback = ''): string
    {
        $class = preg_replace('/[^A-Za-z0-9_\- ]/', '', (string) $class) ?? '';

        return $class !== '' ? $class : $fallback;
    }
}

if (! function_exists('sanitize_email')) {
    function sanitize_email($email): string
    {
        $email = filter_var(trim((string) $email), FILTER_SANITIZE_EMAIL);

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }
}

if (! function_exists('sanitize_user')) {
    function sanitize_user($username, bool $strict = false): string
    {
        $username = wp_strip_all_tags((string) $username);

        return $strict ? (preg_replace('/[^a-zA-Z0-9 _.\-@]/', '', $username) ?? '') : $username;
    }
}

if (! function_exists('sanitize_file_name')) {
    function sanitize_file_name($filename): string
    {
        $filename = preg_replace('/[^A-Za-z0-9_\.\-]/', '-', (string) $filename) ?? '';

        return trim(preg_replace('/-+/', '-', $filename) ?? $filename, '-.');
    }
}

if (! function_exists('sanitize_hex_color')) {
    function sanitize_hex_color($color): string
    {
        return preg_match('/^#([A-Fa-f0-9]{3}){1,2}$/', (string) $color) === 1 ? (string) $color : '';
    }
}

if (! function_exists('sanitize_hex_color_no_hash')) {
    function sanitize_hex_color_no_hash($color): string
    {
        $color = ltrim((string) $color, '#');

        return sanitize_hex_color('#' . $color) === '' ? '' : $color;
    }
}

// -- Text transforms ------------------------------------------------------------

if (! function_exists('wpautop')) {
    function wpautop($text, bool $br = true): string
    {
        $text = (string) $text;

        if (trim($text) === '') {
            return '';
        }

        $blocks = 'table|thead|tfoot|caption|col|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|form|map|area|blockquote|address|style|p|h[1-6]|hr|fieldset|legend|section|article|aside|hgroup|header|footer|nav|figure|figcaption|details|menu|summary';

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('!(<(?:' . $blocks . ')[^>]*>)!', "\n$1", $text) ?? $text;
        $text = preg_replace('!(</(?:' . $blocks . ')>)!', "$1\n\n", $text) ?? $text;

        $paragraphs = preg_split('/\n\s*\n/', trim($text)) ?: [];
        $out        = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);

            if ($paragraph === '') {
                continue;
            }

            // Do not wrap a chunk that is already a block-level element.
            if (preg_match('!^\s*<(?:' . $blocks . ')[\s>/]!i', $paragraph) === 1) {
                $out .= $paragraph . "\n";
                continue;
            }

            $out .= '<p>' . ($br ? preg_replace('/\n/', "<br />\n", $paragraph) : $paragraph) . "</p>\n";
        }

        return $out;
    }
}

if (! function_exists('wptexturize')) {
    function wptexturize($text, bool $reset = false): string
    {
        return (string) $text; // curly-quote substitution is deliberately skipped
    }
}

if (! function_exists('convert_smilies')) {
    function convert_smilies($text): string
    {
        return (string) $text;
    }
}

if (! function_exists('convert_chars')) {
    function convert_chars($content, string $deprecated = ''): string
    {
        return (string) $content;
    }
}

if (! function_exists('force_balance_tags')) {
    function force_balance_tags($text): string
    {
        return (string) $text;
    }
}

if (! function_exists('wp_trim_words')) {
    function wp_trim_words($text, int $num_words = 55, ?string $more = null): string
    {
        $more ??= ' &hellip;';
        $text   = wp_strip_all_tags((string) $text, true);
        $words  = preg_split('/[\n\r\t ]+/', $text, $num_words + 1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($words) > $num_words) {
            array_pop($words);

            return apply_filters('wp_trim_words', implode(' ', $words) . $more, $num_words, $text, $more);
        }

        return apply_filters('wp_trim_words', implode(' ', $words), $num_words, $text, $more);
    }
}

if (! function_exists('make_clickable')) {
    function make_clickable($text): string
    {
        return preg_replace(
            '#(?<!href="|">)(https?://[^\s<]+)#i',
            '<a href="$1" rel="nofollow">$1</a>',
            (string) $text
        ) ?? (string) $text;
    }
}

if (! function_exists('zeroise')) {
    function zeroise($number, int $threshold): string
    {
        return sprintf('%0' . $threshold . 's', $number);
    }
}

if (! function_exists('wp_normalize_path')) {
    function wp_normalize_path(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        return preg_replace('|(?<=.)/+|', '/', $path) ?? $path;
    }
}

// -- Slashes / arrays --------------------------------------------------------------

if (! function_exists('map_deep')) {
    function map_deep($value, callable $callback)
    {
        if (is_array($value)) {
            return array_map(static fn ($item) => map_deep($item, $callback), $value);
        }

        if (is_object($value)) {
            foreach (get_object_vars($value) as $key => $item) {
                $value->{$key} = map_deep($item, $callback);
            }

            return $value;
        }

        return $callback($value);
    }
}

if (! function_exists('stripslashes_deep')) {
    function stripslashes_deep($value)
    {
        return map_deep($value, static fn ($item) => is_string($item) ? stripslashes($item) : $item);
    }
}

if (! function_exists('wp_unslash')) {
    function wp_unslash($value)
    {
        return stripslashes_deep($value);
    }
}

if (! function_exists('wp_slash')) {
    function wp_slash($value)
    {
        return map_deep($value, static fn ($item) => is_string($item) ? addslashes($item) : $item);
    }
}

if (! function_exists('urlencode_deep')) {
    function urlencode_deep($value)
    {
        return map_deep($value, 'urlencode');
    }
}

if (! function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = []): array
    {
        if (is_object($args)) {
            $args = get_object_vars($args);
        } elseif (is_string($args)) {
            parse_str($args, $parsed);
            $args = $parsed;
        }

        return is_array($args) ? array_merge((array) $defaults, $args) : (array) $defaults;
    }
}

if (! function_exists('wp_parse_list')) {
    function wp_parse_list($input_list): array
    {
        if (! is_array($input_list)) {
            return preg_split('/[\s,]+/', (string) $input_list, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        return $input_list;
    }
}

if (! function_exists('wp_parse_id_list')) {
    function wp_parse_id_list($input_list): array
    {
        return array_values(array_unique(array_map('absint', wp_parse_list($input_list))));
    }
}

if (! function_exists('wp_list_pluck')) {
    function wp_list_pluck($input_list, $field, $index_key = null): array
    {
        $out = [];

        foreach ((array) $input_list as $key => $item) {
            $value = is_object($item) ? ($item->{$field} ?? null) : ($item[$field] ?? null);

            if ($index_key === null) {
                $out[] = $value;
                continue;
            }

            $indexValue = is_object($item) ? ($item->{$index_key} ?? $key) : ($item[$index_key] ?? $key);
            $out[$indexValue] = $value;
        }

        return $out;
    }
}

if (! function_exists('wp_filter_object_list')) {
    function wp_filter_object_list($input_list, $args = [], string $operator = 'and', $field = false): array
    {
        $filtered = [];

        foreach ((array) $input_list as $key => $item) {
            $matched = 0;

            foreach ((array) $args as $argKey => $argValue) {
                $value = is_object($item) ? ($item->{$argKey} ?? null) : ($item[$argKey] ?? null);

                if ($value === $argValue) {
                    $matched++;
                }
            }

            $keep = match (strtolower($operator)) {
                'or'  => $matched > 0,
                'not' => $matched === 0,
                default => $matched === count((array) $args),
            };

            if ($keep) {
                $filtered[$key] = $item;
            }
        }

        return $field ? wp_list_pluck($filtered, $field) : $filtered;
    }
}

if (! function_exists('wp_list_filter')) {
    function wp_list_filter($input_list, $args = [], string $operator = 'AND'): array
    {
        return wp_filter_object_list($input_list, $args, strtolower($operator));
    }
}

if (! function_exists('wp_array_slice_assoc')) {
    function wp_array_slice_assoc(array $input_array, array $keys): array
    {
        return array_intersect_key($input_array, array_flip($keys));
    }
}

if (! function_exists('absint')) {
    function absint($maybeint): int
    {
        return abs((int) $maybeint);
    }
}

if (! function_exists('trailingslashit')) {
    function trailingslashit($value): string
    {
        return untrailingslashit((string) $value) . '/';
    }
}

if (! function_exists('untrailingslashit')) {
    function untrailingslashit($value): string
    {
        return rtrim((string) $value, '/\\');
    }
}

if (! function_exists('wp_json_encode')) {
    function wp_json_encode($data, int $options = 0, int $depth = 512)
    {
        return json_encode($data, $options, $depth);
    }
}

if (! function_exists('maybe_serialize')) {
    function maybe_serialize($data)
    {
        return OptionStore::maybeSerialize($data);
    }
}

if (! function_exists('maybe_unserialize')) {
    function maybe_unserialize($data)
    {
        return OptionStore::maybeUnserialize(is_string($data) ? $data : null) ?? $data;
    }
}

if (! function_exists('is_serialized')) {
    function is_serialized($data, bool $strict = true): bool
    {
        return is_string($data) && preg_match('/^[aOsbdi]:/', $data) === 1;
    }
}

// -- Dates and numbers ----------------------------------------------------------------

if (! function_exists('current_time')) {
    function current_time(string $type, $gmt = 0)
    {
        return match ($type) {
            'timestamp', 'U' => time(),
            'mysql'          => date('Y-m-d H:i:s'),
            default          => date($type),
        };
    }
}

if (! function_exists('mysql2date')) {
    function mysql2date(string $format, string $date, bool $translate = true)
    {
        if ($date === '' || str_starts_with($date, '0000-00-00')) {
            return false;
        }

        $timestamp = strtotime($date);

        if ($timestamp === false) {
            return false;
        }

        return $format === 'U' ? $timestamp : date_i18n($format, $timestamp);
    }
}

if (! function_exists('date_i18n')) {
    function date_i18n(string $format, $timestamp_with_offset = false, bool $gmt = false): string
    {
        $timestamp = $timestamp_with_offset === false ? time() : (int) $timestamp_with_offset;

        return apply_filters('date_i18n', date($format, $timestamp), $format, $timestamp, $gmt);
    }
}

if (! function_exists('wp_date')) {
    function wp_date(string $format, ?int $timestamp = null, $timezone = null): string
    {
        return date_i18n($format, $timestamp ?? time());
    }
}

if (! function_exists('get_gmt_from_date')) {
    function get_gmt_from_date(string $date_string, string $format = 'Y-m-d H:i:s'): string
    {
        return date($format, strtotime($date_string) ?: time());
    }
}

if (! function_exists('get_date_from_gmt')) {
    function get_date_from_gmt(string $date_string, string $format = 'Y-m-d H:i:s'): string
    {
        return date($format, strtotime($date_string) ?: time());
    }
}

if (! function_exists('human_time_diff')) {
    function human_time_diff(int $from, int $to = 0): string
    {
        $to   = $to ?: time();
        $diff = abs($to - $from);

        return match (true) {
            $diff < HOUR_IN_SECONDS  => max(1, (int) round($diff / MINUTE_IN_SECONDS)) . ' mins',
            $diff < DAY_IN_SECONDS   => max(1, (int) round($diff / HOUR_IN_SECONDS)) . ' hours',
            $diff < WEEK_IN_SECONDS  => max(1, (int) round($diff / DAY_IN_SECONDS)) . ' days',
            $diff < MONTH_IN_SECONDS => max(1, (int) round($diff / WEEK_IN_SECONDS)) . ' weeks',
            $diff < YEAR_IN_SECONDS  => max(1, (int) round($diff / MONTH_IN_SECONDS)) . ' months',
            default                  => max(1, (int) round($diff / YEAR_IN_SECONDS)) . ' years',
        };
    }
}

if (! function_exists('number_format_i18n')) {
    function number_format_i18n($number, int $decimals = 0): string
    {
        return apply_filters('number_format_i18n', number_format((float) $number, $decimals), $number, $decimals);
    }
}

if (! function_exists('size_format')) {
    function size_format($bytes, int $decimals = 0)
    {
        $bytes = (float) $bytes;
        $units = ['B' => 1, 'KB' => KB_IN_BYTES, 'MB' => MB_IN_BYTES, 'GB' => GB_IN_BYTES];

        foreach (array_reverse($units) as $unit => $size) {
            if ($bytes >= $size) {
                return number_format_i18n($bytes / $size, $decimals) . ' ' . $unit;
            }
        }

        return $bytes > 0 ? number_format_i18n($bytes, $decimals) . ' B' : false;
    }
}

// -- Form-state helpers ---------------------------------------------------------------

if (! function_exists('__checked_selected_helper')) {
    function __checked_selected_helper($helper, $current, bool $display, string $type): string
    {
        $result = ((string) $helper === (string) $current) ? " {$type}='{$type}'" : '';

        if ($display) {
            echo $result;
        }

        return $result;
    }
}

if (! function_exists('checked')) {
    function checked($checked, $current = true, bool $display = true): string
    {
        return __checked_selected_helper($checked, $current, $display, 'checked');
    }
}

if (! function_exists('selected')) {
    function selected($selected, $current = true, bool $display = true): string
    {
        return __checked_selected_helper($selected, $current, $display, 'selected');
    }
}

if (! function_exists('disabled')) {
    function disabled($disabled, $current = true, bool $display = true): string
    {
        return __checked_selected_helper($disabled, $current, $display, 'disabled');
    }
}

if (! function_exists('wp_readonly')) {
    function wp_readonly($readonly_value, $current = true, bool $display = true): string
    {
        return __checked_selected_helper($readonly_value, $current, $display, 'readonly');
    }
}

// -- Random / ids --------------------------------------------------------------------

if (! function_exists('wp_rand')) {
    function wp_rand(int $min = 0, int $max = 0): int
    {
        $max = $max ?: PHP_INT_MAX;

        return random_int(min($min, $max), max($min, $max));
    }
}

if (! function_exists('wp_generate_password')) {
    function wp_generate_password(int $length = 12, bool $special_chars = true, bool $extra_special_chars = false): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

        if ($special_chars) {
            $chars .= '!@#$%^&*()';
        }

        if ($extra_special_chars) {
            $chars .= '-_ []{}<>~`+=,.;:/?|';
        }

        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return apply_filters('random_password', $password);
    }
}

if (! function_exists('wp_generate_uuid4')) {
    function wp_generate_uuid4(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff), random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
        );
    }
}

if (! function_exists('wp_unique_id')) {
    function wp_unique_id(string $prefix = ''): string
    {
        static $id = 0;

        return $prefix . ++$id;
    }
}
