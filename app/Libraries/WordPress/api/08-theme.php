<?php

use App\Libraries\WordPress\Registry;
use App\Models\MenuItemModel;
use App\Models\MenuModel;

/**
 * Theme-side registration and rendering: supports, directories, nav menus
 * and sidebars. Menus read from the LightCMS `menus`/`menu_items` tables,
 * so a location a WP theme registers is managed from the LightCMS admin.
 */

if (! function_exists('add_theme_support')) {
    function add_theme_support(string $feature, ...$args): void
    {
        Registry::$themeSupports[$feature] = $args === [] ? true : $args;
    }
}

if (! function_exists('remove_theme_support')) {
    function remove_theme_support(string $feature): bool
    {
        unset(Registry::$themeSupports[$feature]);

        return true;
    }
}

if (! function_exists('current_theme_supports')) {
    function current_theme_supports(string $feature, ...$args): bool
    {
        if (! isset(Registry::$themeSupports[$feature])) {
            return false;
        }

        if ($args === []) {
            return true;
        }

        $supported = Registry::$themeSupports[$feature];

        if (! is_array($supported) || ! isset($supported[0]) || ! is_array($supported[0])) {
            return true;
        }

        return in_array($args[0], $supported[0], true);
    }
}

if (! function_exists('get_theme_support')) {
    function get_theme_support(string $feature, ...$args)
    {
        return Registry::$themeSupports[$feature] ?? false;
    }
}

if (! function_exists('get_stylesheet')) {
    function get_stylesheet(): string
    {
        return (string) (wp_themes()->activeSlug() ?? '');
    }
}

if (! function_exists('get_template')) {
    function get_template(): string
    {
        return (string) (wp_themes()->activeParentSlug() ?? get_stylesheet());
    }
}

if (! function_exists('get_stylesheet_directory')) {
    function get_stylesheet_directory(): string
    {
        return wp_themes()->stylesheetPath();
    }
}

if (! function_exists('get_stylesheet_directory_uri')) {
    function get_stylesheet_directory_uri(): string
    {
        return apply_filters('stylesheet_directory_uri', wp_themes()->stylesheetUri());
    }
}

if (! function_exists('get_template_directory')) {
    function get_template_directory(): string
    {
        return wp_themes()->templatePath();
    }
}

if (! function_exists('get_template_directory_uri')) {
    function get_template_directory_uri(): string
    {
        return apply_filters('template_directory_uri', wp_themes()->templateUri());
    }
}

if (! function_exists('get_stylesheet_uri')) {
    function get_stylesheet_uri(): string
    {
        return apply_filters('stylesheet_uri', get_stylesheet_directory_uri() . '/style.css');
    }
}

if (! function_exists('get_theme_root')) {
    function get_theme_root(): string
    {
        return rtrim(wp_themes()->themesDir(), '/');
    }
}

if (! function_exists('get_theme_root_uri')) {
    function get_theme_root_uri(): string
    {
        return rtrim(base_url(config(Config\WordPress::class)->themesPath), '/');
    }
}

if (! function_exists('wp_get_theme')) {
    function wp_get_theme(?string $stylesheet = null, ?string $theme_root = null): WP_Theme
    {
        $slug   = $stylesheet ?? get_stylesheet();
        $header = wp_themes()->get($slug);

        return new WP_Theme($header ?? ['slug' => $slug, 'Name' => $slug]);
    }
}

if (! function_exists('wp_get_themes')) {
    function wp_get_themes(array $args = []): array
    {
        $themes = [];

        foreach (wp_themes()->available() as $slug => $header) {
            $themes[$slug] = new WP_Theme($header);
        }

        return $themes;
    }
}

if (! function_exists('locate_template')) {
    function locate_template($template_names, bool $load = false, bool $load_once = true, array $args = []): string
    {
        foreach ((array) $template_names as $name) {
            $located = wp_themes()->locate((string) $name);

            if ($located !== null) {
                if ($load) {
                    wp_include_file($located, $args);
                }

                return $located;
            }
        }

        return '';
    }
}

if (! function_exists('add_image_size')) {
    function add_image_size(string $name, int $width = 0, int $height = 0, $crop = false): void
    {
        Registry::$imageSizes[$name] = [$width, $height, $crop];
    }
}

if (! function_exists('has_image_size')) {
    function has_image_size(string $name): bool
    {
        return isset(Registry::$imageSizes[$name]);
    }
}

if (! function_exists('remove_image_size')) {
    function remove_image_size(string $name): bool
    {
        unset(Registry::$imageSizes[$name]);

        return true;
    }
}

if (! function_exists('set_post_thumbnail_size')) {
    function set_post_thumbnail_size(int $width = 0, int $height = 0, $crop = false): void
    {
        add_image_size('post-thumbnail', $width, $height, $crop);
    }
}

if (! function_exists('get_intermediate_image_sizes')) {
    function get_intermediate_image_sizes(): array
    {
        return array_merge(['thumbnail', 'medium', 'large'], array_keys(Registry::$imageSizes));
    }
}

// -- Nav menus ------------------------------------------------------------------

if (! function_exists('register_nav_menus')) {
    function register_nav_menus(array $locations = []): void
    {
        Registry::$navMenus = array_merge(Registry::$navMenus, $locations);
    }
}

if (! function_exists('register_nav_menu')) {
    function register_nav_menu(string $location, string $description): void
    {
        register_nav_menus([$location => $description]);
    }
}

if (! function_exists('get_registered_nav_menus')) {
    function get_registered_nav_menus(): array
    {
        return Registry::$navMenus;
    }
}

if (! function_exists('get_nav_menu_locations')) {
    function get_nav_menu_locations(): array
    {
        $locations = [];

        foreach (array_keys(Registry::$navMenus) as $location) {
            $menu = (new MenuModel())->findByLocation($location);

            if ($menu !== null) {
                $locations[$location] = (int) $menu['id'];
            }
        }

        return $locations;
    }
}

if (! function_exists('has_nav_menu')) {
    function has_nav_menu(string $location): bool
    {
        return (new MenuModel())->findByLocation($location) !== null;
    }
}

if (! function_exists('wp_get_nav_menu_items')) {
    /**
     * Flat list of menu-item objects for a location or menu id, shaped the
     * way Walker_Nav_Menu expects (db_id / menu_item_parent / classes).
     */
    function wp_get_nav_menu_items($menu, array $args = [])
    {
        $menuModel = new MenuModel();

        if (is_numeric($menu)) {
            $row = $menuModel->find((int) $menu);
        } else {
            $row = $menuModel->findByLocation((string) $menu) ?? $menuModel->where('slug', $menu)->first();
        }

        if ($row === null) {
            return false;
        }

        $items = (new MenuItemModel())
            ->where('menu_id', $row['id'])
            ->orderBy('position', 'ASC')
            ->findAll();

        $currentUrl = rtrim(wp_current_url(), '/');
        $objects    = [];

        foreach ($items as $item) {
            $url     = (string) ($item['url'] ?? '');
            $url     = preg_match('#^https?://#i', $url) === 1 ? $url : home_url(ltrim($url, '/'));
            $classes = array_filter(explode(' ', (string) ($item['css_class'] ?? '')));

            if (rtrim($url, '/') === $currentUrl) {
                $classes[] = 'current-menu-item';
            }

            $objects[] = (object) [
                'ID'               => (int) $item['id'],
                'db_id'            => (int) $item['id'],
                'menu_item_parent' => (int) ($item['parent_id'] ?? 0),
                'object_id'        => (int) $item['id'],
                'object'           => 'custom',
                'type'             => 'custom',
                'type_label'       => 'Custom Link',
                'title'            => (string) ($item['title'] ?? ''),
                'url'              => $url,
                'target'           => (string) ($item['target'] ?? ''),
                'attr_title'       => '',
                'description'      => '',
                'classes'          => array_values($classes),
                'xfn'              => '',
                'menu_order'       => (int) ($item['position'] ?? 0),
                'current'          => rtrim($url, '/') === $currentUrl,
            ];
        }

        return apply_filters('wp_get_nav_menu_items', $objects, $row, $args);
    }
}

if (! function_exists('wp_get_nav_menu_object')) {
    function wp_get_nav_menu_object($menu)
    {
        $row = is_numeric($menu)
            ? (new MenuModel())->find((int) $menu)
            : ((new MenuModel())->findByLocation((string) $menu) ?? (new MenuModel())->where('slug', $menu)->first());

        return $row === null ? false : (object) ['term_id' => (int) $row['id'], 'name' => $row['name'], 'slug' => $row['slug']];
    }
}

if (! function_exists('wp_nav_menu')) {
    function wp_nav_menu(array $args = [])
    {
        $defaults = [
            'menu' => '', 'container' => 'div', 'container_class' => '', 'container_id' => '',
            'menu_class' => 'menu', 'menu_id' => '', 'echo' => true, 'fallback_cb' => 'wp_page_menu',
            'before' => '', 'after' => '', 'link_before' => '', 'link_after' => '', 'items_wrap' => '<ul id="%1$s" class="%2$s">%3$s</ul>',
            'depth' => 0, 'walker' => '', 'theme_location' => '',
        ];

        $args = (object) wp_parse_args(apply_filters('wp_nav_menu_args', wp_parse_args($args, $defaults)), $defaults);

        $items = wp_get_nav_menu_items($args->theme_location !== '' ? $args->theme_location : $args->menu);

        if ($items === false || $items === []) {
            if ($args->fallback_cb && is_callable($args->fallback_cb)) {
                $fallbackArgs = (array) $args;
                $fallbackArgs['echo'] = false;
                $output = ($args->fallback_cb)($fallbackArgs);

                if ($args->echo) {
                    echo $output;

                    return null;
                }

                return $output;
            }

            return $args->echo ? null : '';
        }

        $walker = ($args->walker !== '' && is_object($args->walker)) ? $args->walker : new Walker_Nav_Menu();
        $items  = $walker->walk($items, (int) $args->depth, $args);

        $menuId    = $args->menu_id !== '' ? $args->menu_id : 'menu-' . sanitize_title((string) ($args->theme_location ?: 'menu'));
        $menuClass = $args->menu_class;

        $navMenu = sprintf($args->items_wrap, esc_attr($menuId), esc_attr($menuClass), $items);

        if ($args->container !== '' && $args->container !== false) {
            $attributes = '';

            if ($args->container_id !== '') {
                $attributes .= ' id="' . esc_attr($args->container_id) . '"';
            }

            if ($args->container_class !== '') {
                $attributes .= ' class="' . esc_attr($args->container_class) . '"';
            }

            $navMenu = '<' . $args->container . $attributes . '>' . $navMenu . '</' . $args->container . '>';
        }

        $navMenu = apply_filters('wp_nav_menu', $navMenu, $args);

        if ($args->echo) {
            echo $navMenu;

            return null;
        }

        return $navMenu;
    }
}

if (! function_exists('wp_page_menu')) {
    function wp_page_menu(array $args = [])
    {
        $args  = wp_parse_args($args, ['echo' => true, 'menu_class' => 'menu', 'container' => 'div']);
        $pages = get_pages(['posts_per_page' => 20]);
        $items = '';

        foreach ($pages as $page) {
            $items .= '<li class="page_item page-item-' . $page->ID . '"><a href="' . esc_url((string) get_permalink($page)) . '">' . esc_html($page->post_title) . '</a></li>';
        }

        $html = '<ul class="' . esc_attr($args['menu_class']) . '">' . $items . '</ul>';

        if ($args['echo']) {
            echo $html;

            return null;
        }

        return $html;
    }
}

if (! function_exists('wp_list_pages')) {
    function wp_list_pages($args = '')
    {
        return wp_page_menu(is_array($args) ? $args : []);
    }
}

// -- Sidebars and widgets -----------------------------------------------------------

if (! function_exists('register_sidebar')) {
    function register_sidebar(array $args = []): string
    {
        static $index = 0;
        $index++;

        $args = wp_parse_args($args, [
            'name'          => 'Sidebar ' . $index,
            'id'            => 'sidebar-' . $index,
            'description'   => '',
            'class'         => '',
            'before_widget' => '<li id="%1$s" class="widget %2$s">',
            'after_widget'  => "</li>\n",
            'before_title'  => '<h2 class="widgettitle">',
            'after_title'   => "</h2>\n",
        ]);

        Registry::$sidebars[$args['id']] = $args;
        $GLOBALS['wp_registered_sidebars'][$args['id']] = $args;

        return $args['id'];
    }
}

if (! function_exists('register_sidebars')) {
    function register_sidebars(int $number = 1, array $args = []): void
    {
        for ($i = 1; $i <= $number; $i++) {
            register_sidebar($args);
        }
    }
}

if (! function_exists('unregister_sidebar')) {
    function unregister_sidebar(string $sidebar_id): void
    {
        unset(Registry::$sidebars[$sidebar_id], $GLOBALS['wp_registered_sidebars'][$sidebar_id]);
    }
}

if (! function_exists('is_registered_sidebar')) {
    function is_registered_sidebar(string $sidebar_id): bool
    {
        return isset(Registry::$sidebars[$sidebar_id]);
    }
}

if (! function_exists('is_active_sidebar')) {
    function is_active_sidebar($index): bool
    {
        $id      = is_int($index) ? 'sidebar-' . $index : (string) $index;
        $widgets = wp_get_sidebars_widgets();

        return ! empty($widgets[$id]);
    }
}

if (! function_exists('wp_get_sidebars_widgets')) {
    function wp_get_sidebars_widgets(): array
    {
        $stored = get_option('sidebars_widgets', []);

        return is_array($stored) ? $stored : [];
    }
}

if (! function_exists('wp_set_sidebars_widgets')) {
    function wp_set_sidebars_widgets(array $sidebars_widgets): void
    {
        update_option('sidebars_widgets', $sidebars_widgets);
    }
}

if (! function_exists('register_widget')) {
    function register_widget($widget): void
    {
        $GLOBALS['wp_widget_factory']->register($widget);
    }
}

if (! function_exists('unregister_widget')) {
    function unregister_widget($widget): void
    {
        $GLOBALS['wp_widget_factory']->unregister($widget);
    }
}

if (! function_exists('wp_register_sidebar_widget')) {
    function wp_register_sidebar_widget($id, string $name, $output_callback, array $options = [], ...$params): void
    {
        $GLOBALS['wp_registered_widgets'][$id] = [
            'name'     => $name,
            'id'       => $id,
            'callback' => $output_callback,
            'params'   => $params,
        ];
    }
}

if (! function_exists('dynamic_sidebar')) {
    function dynamic_sidebar($index = 1): bool
    {
        $id      = is_int($index) ? 'sidebar-' . $index : (string) $index;
        $sidebar = Registry::$sidebars[$id] ?? null;

        if ($sidebar === null) {
            return false;
        }

        $placed  = wp_get_sidebars_widgets()[$id] ?? [];
        $factory = $GLOBALS['wp_widget_factory'];
        $rendered = false;

        foreach ($placed as $widgetId) {
            // Widget ids look like "text-3": id_base plus instance number.
            if (preg_match('/^(.+)-(\d+)$/', (string) $widgetId, $match) !== 1) {
                continue;
            }

            $widget = $factory->get_widget_object($match[1]);

            if ($widget === null) {
                continue;
            }

            $params = [
                'name'          => $sidebar['name'],
                'id'            => $id,
                'before_widget' => sprintf($sidebar['before_widget'], esc_attr((string) $widgetId), esc_attr($widget->widget_options['classname'] ?? '')),
                'after_widget'  => $sidebar['after_widget'],
                'before_title'  => $sidebar['before_title'],
                'after_title'   => $sidebar['after_title'],
                'widget_id'     => $widgetId,
                'widget_name'   => $widget->name,
            ];

            $widget->display_callback($params, (int) $match[2]);
            $rendered = true;
        }

        do_action('dynamic_sidebar_after', $id, true);

        return $rendered;
    }
}

if (! function_exists('the_widget')) {
    function the_widget(string $widget, $instance = [], $args = []): void
    {
        if (! class_exists($widget)) {
            return;
        }

        $object = new $widget();
        $args   = wp_parse_args($args, [
            'before_widget' => '<div class="widget ' . esc_attr($object->widget_options['classname'] ?? '') . '">',
            'after_widget'  => '</div>',
            'before_title'  => '<h2 class="widgettitle">',
            'after_title'   => '</h2>',
        ]);

        $object->_set(-1);
        $object->widget($args, wp_parse_args($instance));
    }
}
