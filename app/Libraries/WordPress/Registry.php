<?php

namespace App\Libraries\WordPress;

/**
 * Everything a theme or plugin *registers* at boot: theme supports, menu
 * locations, sidebars, widgets, image sizes, post types, taxonomies,
 * shortcodes, admin pages, settings and REST routes.
 *
 * One flat container rather than a class per concept — registration is
 * write-once-at-boot, read-many-at-render, and keeping it together makes
 * `php spark wp:info` able to dump the whole picture.
 */
class Registry
{
    /** @var array<string, mixed> feature => true|args */
    public static array $themeSupports = [];

    /** @var array<string, string> location => description */
    public static array $navMenus = [];

    /** @var array<string, array<string, mixed>> sidebar id => args */
    public static array $sidebars = [];

    /** @var array<string, list<array{callback: mixed, params: array, name: string, id: string}>> */
    public static array $sidebarWidgets = [];

    /** @var array<string, object> widget class => instance */
    public static array $widgets = [];

    /** @var array<string, array{0: int, 1: int, 2: bool|array}> */
    public static array $imageSizes = [];

    /** @var array<string, object> post type => args object */
    public static array $postTypes = [];

    /** @var array<string, object> taxonomy => args object */
    public static array $taxonomies = [];

    /** @var array<string, callable> tag => handler */
    public static array $shortcodes = [];

    /** @var list<array<string, mixed>> */
    public static array $adminMenus = [];

    /** @var array<string, list<array<string, mixed>>> parent slug => submenu items */
    public static array $adminSubmenus = [];

    /** @var array<string, array<string, mixed>> "namespace/route" => args */
    public static array $restRoutes = [];

    /** @var array<string, array<string, mixed>> */
    public static array $settings = [];
    public static array $settingsSections = [];
    public static array $settingsFields = [];

    /** @var array<string, string> domain => absolute .mo path */
    public static array $textDomains = [];

    /** @var array{settings: array, controls: array, sections: array, panels: array} */
    public static array $customize = ['settings' => [], 'controls' => [], 'sections' => [], 'panels' => []];

    /** @var list<array{type: string, message: string}> */
    public static array $adminNotices = [];

    /** Deferred until the end of the response, like WP's shutdown queue. */
    public static array $shutdown = [];

    public static function reset(): void
    {
        static::$themeSupports    = [];
        static::$navMenus         = [];
        static::$sidebars         = [];
        static::$sidebarWidgets   = [];
        static::$widgets          = [];
        static::$imageSizes       = [];
        static::$postTypes        = [];
        static::$taxonomies       = [];
        static::$shortcodes       = [];
        static::$adminMenus       = [];
        static::$adminSubmenus    = [];
        static::$restRoutes       = [];
        static::$settings         = [];
        static::$settingsSections = [];
        static::$settingsFields   = [];
        static::$textDomains      = [];
        static::$customize        = ['settings' => [], 'controls' => [], 'sections' => [], 'panels' => []];
        static::$adminNotices     = [];
        static::$shutdown         = [];
    }

    /** Post types that map onto a real LightCMS `posts.post_type` value. */
    public static function publicPostTypes(): array
    {
        $types = ['post', 'page'];

        foreach (static::$postTypes as $name => $args) {
            if (! empty($args->public)) {
                $types[] = $name;
            }
        }

        return array_values(array_unique($types));
    }
}
