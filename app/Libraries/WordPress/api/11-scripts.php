<?php

/**
 * wp_enqueue_* and friends. The queue itself lives in Assets; these are
 * the thin global wrappers themes call.
 */

if (! function_exists('wp_register_style')) {
    function wp_register_style(string $handle, $src, array $deps = [], $ver = false, string $media = 'all'): bool
    {
        wp_assets()->registerStyle($handle, $src, $deps, $ver, $media);

        return true;
    }
}

if (! function_exists('wp_enqueue_style')) {
    function wp_enqueue_style(string $handle, string $src = '', array $deps = [], $ver = false, string $media = 'all'): void
    {
        wp_assets()->enqueueStyle($handle, $src, $deps, $ver, $media);
    }
}

if (! function_exists('wp_dequeue_style')) {
    function wp_dequeue_style(string $handle): void
    {
        wp_assets()->dequeueStyle($handle);
    }
}

if (! function_exists('wp_deregister_style')) {
    function wp_deregister_style(string $handle): void
    {
        wp_assets()->deregisterStyle($handle);
    }
}

if (! function_exists('wp_add_inline_style')) {
    function wp_add_inline_style(string $handle, string $data): bool
    {
        return wp_assets()->addInlineStyle($handle, $data);
    }
}

if (! function_exists('wp_style_is')) {
    function wp_style_is(string $handle, string $status = 'enqueued'): bool
    {
        return wp_assets()->isEnqueued($handle, 'style', $status);
    }
}

if (! function_exists('wp_register_script')) {
    function wp_register_script(string $handle, $src, array $deps = [], $ver = false, $args = false): bool
    {
        wp_assets()->registerScript($handle, $src, $deps, $ver, $args);

        return true;
    }
}

if (! function_exists('wp_enqueue_script')) {
    function wp_enqueue_script(string $handle, string $src = '', array $deps = [], $ver = false, $args = false): void
    {
        wp_assets()->enqueueScript($handle, $src, $deps, $ver, $args);
    }
}

if (! function_exists('wp_dequeue_script')) {
    function wp_dequeue_script(string $handle): void
    {
        wp_assets()->dequeueScript($handle);
    }
}

if (! function_exists('wp_deregister_script')) {
    function wp_deregister_script(string $handle): void
    {
        wp_assets()->deregisterScript($handle);
    }
}

if (! function_exists('wp_localize_script')) {
    function wp_localize_script(string $handle, string $object_name, array $l10n): bool
    {
        return wp_assets()->localize($handle, $object_name, $l10n);
    }
}

if (! function_exists('wp_add_inline_script')) {
    function wp_add_inline_script(string $handle, string $data, string $position = 'after'): bool
    {
        return wp_assets()->addInlineScript($handle, $data, $position);
    }
}

if (! function_exists('wp_script_is')) {
    function wp_script_is(string $handle, string $status = 'enqueued'): bool
    {
        return wp_assets()->isEnqueued($handle, 'script', $status);
    }
}

if (! function_exists('wp_script_add_data')) {
    function wp_script_add_data(string $handle, string $key, $value): bool
    {
        return true;
    }
}

if (! function_exists('wp_print_styles')) {
    function wp_print_styles($handles = false): void
    {
        echo wp_assets()->printStyles();
    }
}

if (! function_exists('wp_print_scripts')) {
    function wp_print_scripts($handles = false): void
    {
        echo wp_assets()->printScripts(false);
    }
}

if (! function_exists('wp_print_footer_scripts')) {
    function wp_print_footer_scripts(): void
    {
        echo wp_assets()->printScripts(true);
    }
}

if (! function_exists('wp_enqueue_media')) {
    function wp_enqueue_media(array $args = []): void
    {
        // The WP media modal is not part of the compat layer; LightCMS has
        // its own media picker in the admin.
    }
}
