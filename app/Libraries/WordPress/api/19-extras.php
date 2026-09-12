<?php

use App\Models\MenuItemModel;
use App\Models\MenuModel;

/**
 * Functions that classic themes reach for often enough to matter, but
 * that do not belong to any one area above: queried-object accessors,
 * ancestor/breadcrumb helpers, filesystem paths, and small utilities.
 *
 * This file is where to add anything `php spark wp:doctor` reports as
 * missing for a theme you want to run.
 */

if (! function_exists('get_queried_object')) {
    function get_queried_object()
    {
        return $GLOBALS['wp_query']?->get_queried_object();
    }
}

if (! function_exists('get_queried_object_id')) {
    function get_queried_object_id(): int
    {
        return (int) ($GLOBALS['wp_query']?->get_queried_object_id() ?? 0);
    }
}

if (! function_exists('post_type_archive_title')) {
    function post_type_archive_title(string $prefix = '', bool $display = true)
    {
        $postType = (string) ($GLOBALS['wp_query']->query_vars['post_type'] ?? 'post');
        $object   = get_post_type_object($postType);
        $title    = (string) ($object->labels->name ?? ucfirst($postType));

        if ($display) {
            echo $prefix . esc_html($title);

            return null;
        }

        return $prefix . $title;
    }
}

if (! function_exists('get_post_ancestors')) {
    function get_post_ancestors($post): array
    {
        // LightCMS posts have no parent column, so every post is a root.
        return [];
    }
}

if (! function_exists('get_category_parents')) {
    function get_category_parents($category_id, bool $link = false, string $separator = '/', bool $nicename = false, array $deprecated = [])
    {
        $chain = [];
        $term  = get_term((int) $category_id, 'category');
        $guard = 0;

        while ($term instanceof WP_Term && $guard++ < 20) {
            $name    = $nicename ? $term->slug : $term->name;
            $chain[] = $link
                ? '<a href="' . esc_url((string) get_category_link($term->term_id)) . '">' . esc_html($name) . '</a>'
                : $name;

            if ($term->parent === 0) {
                break;
            }

            $term = get_term($term->parent, 'category');
        }

        if ($chain === []) {
            return new WP_Error('invalid_term', 'Category not found.');
        }

        return implode($separator, array_reverse($chain)) . $separator;
    }
}

if (! function_exists('category_description')) {
    function category_description($category = 0): string
    {
        $term = $category !== 0 ? get_term((int) $category, 'category') : get_queried_object();

        return apply_filters('category_description', $term instanceof WP_Term ? $term->description : '');
    }
}

if (! function_exists('term_description')) {
    function term_description($term = 0, string $taxonomy = ''): string
    {
        $found = $term !== 0 ? get_term((int) $term, $taxonomy) : get_queried_object();

        return apply_filters('term_description', $found instanceof WP_Term ? $found->description : '');
    }
}

if (! function_exists('attachment_url_to_postid')) {
    function attachment_url_to_postid(string $url): int
    {
        $path = ltrim(str_replace(rtrim(base_url(), '/'), '', $url), '/');
        $row  = (new App\Models\MediaModel())->where('filepath', $path)->first();

        return $row ? (int) $row['id'] : 0;
    }
}

if (! function_exists('wp_mkdir_p')) {
    function wp_mkdir_p(string $target): bool
    {
        $target = rtrim($target, '/\\');

        if ($target === '' || is_dir($target)) {
            return is_dir($target);
        }

        return mkdir($target, 0755, true) || is_dir($target);
    }
}

if (! function_exists('get_temp_dir')) {
    function get_temp_dir(): string
    {
        return trailingslashit(WRITEPATH . 'temp');
    }
}

if (! function_exists('wp_is_writable')) {
    function wp_is_writable(string $path): bool
    {
        return is_writable($path);
    }
}

if (! function_exists('wp_specialchars_decode')) {
    function wp_specialchars_decode($content, $quote_style = ENT_NOQUOTES): string
    {
        return html_entity_decode((string) $content, is_int($quote_style) ? $quote_style : ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('wp_is_mobile')) {
    function wp_is_mobile(): bool
    {
        $agent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

        return $agent !== '' && preg_match('/Mobile|Android|Silk\/|Kindle|BlackBerry|Opera Mini|Opera Mobi/i', $agent) === 1;
    }
}

if (! function_exists('wp_lostpassword_url')) {
    function wp_lostpassword_url(string $redirect = ''): string
    {
        return admin_url('login');
    }
}

if (! function_exists('wp_admin_css')) {
    function wp_admin_css(string $file = 'wp-admin', bool $force_echo = false): void
    {
    }
}

if (! function_exists('wp_editor')) {
    /**
     * The TinyMCE editor is not part of the compat layer (LightCMS has its
     * own block editor), so this renders a plain textarea with the same
     * name/id contract, which keeps admin screens usable.
     */
    function wp_editor(string $content, string $editor_id, array $settings = []): void
    {
        $name = (string) ($settings['textarea_name'] ?? $editor_id);
        $rows = (int) ($settings['textarea_rows'] ?? 10);

        echo '<textarea id="' . esc_attr($editor_id) . '" name="' . esc_attr($name) . '" rows="' . $rows . '" class="widefat">'
            . esc_textarea($content) . '</textarea>';
    }
}

if (! function_exists('wp_styles')) {
    function wp_styles(): WP_Styles
    {
        return $GLOBALS['wp_styles'] ??= new WP_Styles();
    }
}

if (! function_exists('wp_scripts')) {
    function wp_scripts(): WP_Scripts
    {
        return $GLOBALS['wp_scripts'] ??= new WP_Scripts();
    }
}

// -- Menu writes -----------------------------------------------------------------

if (! function_exists('wp_create_nav_menu')) {
    function wp_create_nav_menu(string $menu_name)
    {
        $model = new MenuModel();
        $slug  = sanitize_title($menu_name);

        if (($existing = $model->where('slug', $slug)->first()) !== null) {
            return (int) $existing['id'];
        }

        return (int) $model->insert(['name' => $menu_name, 'slug' => $slug], true);
    }
}

if (! function_exists('wp_update_nav_menu_item')) {
    function wp_update_nav_menu_item(int $menu_id = 0, int $menu_item_db_id = 0, array $menu_item_data = [])
    {
        $model = new MenuItemModel();

        $row = [
            'menu_id'   => $menu_id,
            'parent_id' => (int) ($menu_item_data['menu-item-parent-id'] ?? 0),
            'title'     => (string) ($menu_item_data['menu-item-title'] ?? ''),
            'url'       => (string) ($menu_item_data['menu-item-url'] ?? ''),
            'target'    => (string) ($menu_item_data['menu-item-target'] ?? ''),
            'css_class' => (string) ($menu_item_data['menu-item-classes'] ?? ''),
            'position'  => (int) ($menu_item_data['menu-item-position'] ?? 0),
        ];

        if ($menu_item_db_id > 0 && $model->find($menu_item_db_id) !== null) {
            $model->update($menu_item_db_id, $row);

            return $menu_item_db_id;
        }

        return (int) $model->insert($row, true);
    }
}

if (! function_exists('wp_set_nav_menu_location')) {
    function wp_set_nav_menu_location(int $menu_id, string $location): bool
    {
        return (bool) (new MenuModel())->update($menu_id, ['location' => $location]);
    }
}

// -- Small return-value helpers WordPress ships ------------------------------------

if (! function_exists('__return_true')) {
    function __return_true(): bool
    {
        return true;
    }
}

if (! function_exists('__return_false')) {
    function __return_false(): bool
    {
        return false;
    }
}

if (! function_exists('__return_zero')) {
    function __return_zero(): int
    {
        return 0;
    }
}

if (! function_exists('__return_empty_array')) {
    function __return_empty_array(): array
    {
        return [];
    }
}

if (! function_exists('__return_empty_string')) {
    function __return_empty_string(): string
    {
        return '';
    }
}

if (! function_exists('__return_null')) {
    function __return_null()
    {
        return null;
    }
}
