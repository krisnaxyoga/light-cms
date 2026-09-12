<?php

namespace App\Libraries\Theme;

/**
 * Static registry used by a theme's functions.php (PRD §3.3.A.3).
 * Registration happens early (when functions.php loads); actual
 * output happens later from the layout via ThemeEngine::renderStyles()/
 * renderScripts() and the theme_helper.php wrappers.
 */
class Theme
{
    protected static array $menus = [];
    protected static array $widgetAreas = [];
    protected static array $supports = [];
    protected static array $styles = [];
    protected static array $scripts = [];

    public static function reset(): void
    {
        static::$menus       = [];
        static::$widgetAreas = [];
        static::$supports    = [];
        static::$styles      = [];
        static::$scripts     = [];
    }

    /**
     * @param array<string, string> $menus slug => label
     */
    public static function registerMenus(array $menus): void
    {
        static::$menus = array_merge(static::$menus, $menus);
    }

    public static function registerWidgetArea(string $slug, array $meta): void
    {
        static::$widgetAreas[$slug] = $meta;
    }

    public static function addSupport(string $feature): void
    {
        static::$supports[$feature] = true;
    }

    public static function supports(string $feature): bool
    {
        return isset(static::$supports[$feature]);
    }

    public static function enqueueStyle(string $handle, string $src, array $deps = []): void
    {
        static::$styles[$handle] = ['src' => $src, 'deps' => $deps];
    }

    public static function enqueueScript(string $handle, string $src, array $deps = [], bool $defer = true): void
    {
        static::$scripts[$handle] = ['src' => $src, 'deps' => $deps, 'defer' => $defer];
    }

    public static function menus(): array
    {
        return static::$menus;
    }

    public static function widgetAreas(): array
    {
        return static::$widgetAreas;
    }

    public static function styles(): array
    {
        return static::$styles;
    }

    public static function scripts(): array
    {
        return static::$scripts;
    }
}
