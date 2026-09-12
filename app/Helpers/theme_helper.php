<?php

use Config\Services;

if (! function_exists('theme_engine')) {
    function theme_engine(): \App\Libraries\Theme\ThemeEngine
    {
        return Services::themeEngine();
    }
}

if (! function_exists('theme_header')) {
    function theme_header(array $data = []): void
    {
        echo theme_engine()->getHeader($data);
    }
}

if (! function_exists('theme_footer')) {
    function theme_footer(array $data = []): void
    {
        echo theme_engine()->getFooter($data);
    }
}

if (! function_exists('theme_sidebar')) {
    function theme_sidebar(array $data = []): void
    {
        echo theme_engine()->getSidebar($data);
    }
}

if (! function_exists('theme_render')) {
    function theme_render(string $template, array $data = []): void
    {
        echo theme_engine()->render($template, $data);
    }
}

if (! function_exists('theme_url')) {
    function theme_url(string $path = ''): string
    {
        return theme_engine()->themeUrl($path);
    }
}

if (! function_exists('theme_styles')) {
    function theme_styles(): void
    {
        echo theme_engine()->renderStyles();
    }
}

if (! function_exists('theme_scripts')) {
    function theme_scripts(): void
    {
        echo theme_engine()->renderScripts();
    }
}

if (! function_exists('theme_option')) {
    /**
     * Read a value out of the active theme's `settings` JSON column
     * (populated from theme.json defaults + the Customizer, PRD §3.3.B).
     */
    function theme_option(string $key, mixed $default = null): mixed
    {
        static $settings = null;

        if ($settings === null) {
            $theme    = (new \App\Models\ThemeModel())->getActive();
            $settings = $theme['settings'] ?? [];
        }

        return $settings[$key] ?? $default;
    }
}

if (! function_exists('site_setting')) {
    /**
     * Read a global, site-wide setting (settings table) — not to be
     * confused with theme_option(), which reads the active theme's own
     * `settings` JSON column (per-theme Customizer values).
     */
    function site_setting(string $key, mixed $default = null): mixed
    {
        static $settings = null;

        if ($settings === null) {
            $settings = (new \App\Models\SettingModel())->getAutoloaded();
        }

        return $settings[$key] ?? $default;
    }
}

if (! function_exists('theme_menu')) {
    /**
     * Nested menu items for a registered menu location (PRD §3.3.A.3).
     */
    function theme_menu(string $location): array
    {
        $menu = (new \App\Models\MenuModel())->findByLocation($location);

        if (! $menu) {
            return [];
        }

        return (new \App\Models\MenuItemModel())->treeForMenu((int) $menu['id']);
    }
}
