<?php

use App\Libraries\WordPress\Renderer;
use App\Libraries\WordPress\Runtime;
use Config\WordPress as WordPressConfig;

/**
 * Bridge helpers between LightCMS controllers and the WordPress compat
 * layer. Deliberately prefixed `lcms_` so they can never collide with a
 * real WordPress function name.
 */

if (! function_exists('lcms_wp_enabled')) {
    function lcms_wp_enabled(): bool
    {
        return config(WordPressConfig::class)->enabled;
    }
}

if (! function_exists('lcms_wp_boot')) {
    /**
     * Boot the compat layer to the requested level:
     *   'core'    – constants + API functions only
     *   'plugins' – core + active plugins (admin needs this)
     *   'theme'   – full boot, including the active WP theme's functions.php
     */
    function lcms_wp_boot(string $level = 'theme'): Runtime
    {
        $runtime = Runtime::instance();

        if (! lcms_wp_enabled()) {
            return $runtime;
        }

        return match ($level) {
            'core'    => $runtime->bootCore(),
            'plugins' => $runtime->bootPlugins(),
            default   => $runtime->boot(),
        };
    }
}

if (! function_exists('lcms_wp_active')) {
    /**
     * True when the active theme is a WordPress theme.
     *
     * Booting the core level as a side effect keeps one invariant that
     * every caller depends on: if this returns true, the WP_* classes and
     * API functions exist, so code may use WP_Term/WP_Post immediately
     * without a separate boot call.
     */
    function lcms_wp_active(): bool
    {
        if (! lcms_wp_enabled() || ! Runtime::instance()->themes()->isActive()) {
            return false;
        }

        Runtime::instance()->bootCore();

        return true;
    }
}

if (! function_exists('lcms_wp_renderer')) {
    function lcms_wp_renderer(): Renderer
    {
        return new Renderer(lcms_wp_boot('theme'));
    }
}
