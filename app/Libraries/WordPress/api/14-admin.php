<?php

use App\Libraries\WordPress\Registry;

/**
 * Admin-side API: menu pages, the Settings API, nonces and notices.
 *
 * Pages registered with add_menu_page()/add_submenu_page() are rendered
 * inside the LightCMS admin at admin/wp/<slug> by Admin\WPPageController,
 * with $_GET['page'] set — so a plugin's settings screen works unchanged.
 */

if (! function_exists('add_menu_page')) {
    function add_menu_page(string $page_title, string $menu_title, string $capability, string $menu_slug, $callback = '', string $icon_url = '', $position = null): string
    {
        Registry::$adminMenus[] = [
            'page_title' => $page_title,
            'menu_title' => $menu_title,
            'capability' => $capability,
            'menu_slug'  => $menu_slug,
            'callback'   => $callback,
            'icon_url'   => $icon_url,
            'position'   => $position,
        ];

        return 'toplevel_page_' . $menu_slug;
    }
}

if (! function_exists('add_submenu_page')) {
    function add_submenu_page(string $parent_slug, string $page_title, string $menu_title, string $capability, string $menu_slug, $callback = '', $position = null)
    {
        Registry::$adminSubmenus[$parent_slug][] = [
            'page_title' => $page_title,
            'menu_title' => $menu_title,
            'capability' => $capability,
            'menu_slug'  => $menu_slug,
            'callback'   => $callback,
            'position'   => $position,
        ];

        return $parent_slug . '_page_' . $menu_slug;
    }
}

if (! function_exists('add_options_page')) {
    function add_options_page(string $page_title, string $menu_title, string $capability, string $menu_slug, $callback = '', $position = null)
    {
        return add_submenu_page('options-general.php', $page_title, $menu_title, $capability, $menu_slug, $callback, $position);
    }
}

if (! function_exists('add_theme_page')) {
    function add_theme_page(string $page_title, string $menu_title, string $capability, string $menu_slug, $callback = '', $position = null)
    {
        return add_submenu_page('themes.php', $page_title, $menu_title, $capability, $menu_slug, $callback, $position);
    }
}

if (! function_exists('add_management_page')) {
    function add_management_page(string $page_title, string $menu_title, string $capability, string $menu_slug, $callback = '', $position = null)
    {
        return add_submenu_page('tools.php', $page_title, $menu_title, $capability, $menu_slug, $callback, $position);
    }
}

if (! function_exists('add_dashboard_page')) {
    function add_dashboard_page(string $page_title, string $menu_title, string $capability, string $menu_slug, $callback = '', $position = null)
    {
        return add_submenu_page('index.php', $page_title, $menu_title, $capability, $menu_slug, $callback, $position);
    }
}

if (! function_exists('add_posts_page')) {
    function add_posts_page(string $page_title, string $menu_title, string $capability, string $menu_slug, $callback = '', $position = null)
    {
        return add_submenu_page('edit.php', $page_title, $menu_title, $capability, $menu_slug, $callback, $position);
    }
}

if (! function_exists('add_pages_page')) {
    function add_pages_page(string $page_title, string $menu_title, string $capability, string $menu_slug, $callback = '', $position = null)
    {
        return add_submenu_page('edit.php?post_type=page', $page_title, $menu_title, $capability, $menu_slug, $callback, $position);
    }
}

if (! function_exists('add_media_page')) {
    function add_media_page(string $page_title, string $menu_title, string $capability, string $menu_slug, $callback = '', $position = null)
    {
        return add_submenu_page('upload.php', $page_title, $menu_title, $capability, $menu_slug, $callback, $position);
    }
}

if (! function_exists('add_users_page')) {
    function add_users_page(string $page_title, string $menu_title, string $capability, string $menu_slug, $callback = '', $position = null)
    {
        return add_submenu_page('users.php', $page_title, $menu_title, $capability, $menu_slug, $callback, $position);
    }
}

if (! function_exists('add_plugins_page')) {
    function add_plugins_page(string $page_title, string $menu_title, string $capability, string $menu_slug, $callback = '', $position = null)
    {
        return add_submenu_page('plugins.php', $page_title, $menu_title, $capability, $menu_slug, $callback, $position);
    }
}

if (! function_exists('remove_menu_page')) {
    function remove_menu_page(string $menu_slug)
    {
        foreach (Registry::$adminMenus as $index => $menu) {
            if ($menu['menu_slug'] === $menu_slug) {
                unset(Registry::$adminMenus[$index]);
                Registry::$adminMenus = array_values(Registry::$adminMenus);

                return $menu;
            }
        }

        return false;
    }
}

if (! function_exists('remove_submenu_page')) {
    function remove_submenu_page(string $menu_slug, string $submenu_slug)
    {
        foreach (Registry::$adminSubmenus[$menu_slug] ?? [] as $index => $item) {
            if ($item['menu_slug'] === $submenu_slug) {
                unset(Registry::$adminSubmenus[$menu_slug][$index]);
                Registry::$adminSubmenus[$menu_slug] = array_values(Registry::$adminSubmenus[$menu_slug]);

                return $item;
            }
        }

        return false;
    }
}

if (! function_exists('menu_page_url')) {
    function menu_page_url(string $menu_slug, bool $display = true)
    {
        $url = admin_url('wp/' . $menu_slug);

        if ($display) {
            echo esc_url($url);

            return null;
        }

        return $url;
    }
}

if (! function_exists('get_admin_page_title')) {
    function get_admin_page_title(): string
    {
        return (string) ($GLOBALS['title'] ?? '');
    }
}

// -- Settings API ------------------------------------------------------------

if (! function_exists('register_setting')) {
    function register_setting(string $option_group, string $option_name, $args = []): void
    {
        Registry::$settings[$option_group][$option_name] = wp_parse_args($args, [
            'type'              => 'string',
            'description'       => '',
            'sanitize_callback' => null,
            'default'           => null,
        ]);
    }
}

if (! function_exists('unregister_setting')) {
    function unregister_setting(string $option_group, string $option_name): void
    {
        unset(Registry::$settings[$option_group][$option_name]);
    }
}

if (! function_exists('add_settings_section')) {
    function add_settings_section(string $id, string $title, $callback, string $page, array $args = []): void
    {
        Registry::$settingsSections[$page][$id] = [
            'id' => $id, 'title' => $title, 'callback' => $callback,
        ] + $args;
    }
}

if (! function_exists('add_settings_field')) {
    function add_settings_field(string $id, string $title, $callback, string $page, string $section = 'default', array $args = []): void
    {
        Registry::$settingsFields[$page][$section][$id] = [
            'id' => $id, 'title' => $title, 'callback' => $callback, 'args' => $args,
        ];
    }
}

if (! function_exists('do_settings_sections')) {
    function do_settings_sections(string $page): void
    {
        foreach (Registry::$settingsSections[$page] ?? [] as $section) {
            if ($section['title'] !== '') {
                echo '<h2>' . esc_html($section['title']) . '</h2>';
            }

            if (is_callable($section['callback'])) {
                ($section['callback'])($section);
            }

            echo '<table class="form-table" role="presentation">';
            do_settings_fields($page, $section['id']);
            echo '</table>';
        }
    }
}

if (! function_exists('do_settings_fields')) {
    function do_settings_fields(string $page, string $section): void
    {
        foreach (Registry::$settingsFields[$page][$section] ?? [] as $field) {
            $labelFor = $field['args']['label_for'] ?? '';

            echo '<tr><th scope="row">';
            echo $labelFor !== ''
                ? '<label for="' . esc_attr($labelFor) . '">' . esc_html($field['title']) . '</label>'
                : esc_html($field['title']);
            echo '</th><td>';

            if (is_callable($field['callback'])) {
                ($field['callback'])($field['args']);
            }

            echo '</td></tr>';
        }
    }
}

if (! function_exists('settings_fields')) {
    function settings_fields(string $option_group): void
    {
        echo '<input type="hidden" name="option_page" value="' . esc_attr($option_group) . '">';
        echo '<input type="hidden" name="action" value="update">';
        wp_nonce_field($option_group . '-options');
    }
}

if (! function_exists('add_settings_error')) {
    function add_settings_error(string $setting, string $code, string $message, string $type = 'error'): void
    {
        Registry::$adminNotices[] = ['type' => $type, 'message' => $message];
    }
}

if (! function_exists('get_settings_errors')) {
    function get_settings_errors(string $setting = '', bool $sanitize = false): array
    {
        return Registry::$adminNotices;
    }
}

if (! function_exists('settings_errors')) {
    function settings_errors(string $setting = '', bool $sanitize = false, bool $hide_on_update = false): void
    {
        foreach (Registry::$adminNotices as $notice) {
            echo '<div class="notice notice-' . esc_attr($notice['type']) . '"><p>' . esc_html($notice['message']) . '</p></div>';
        }
    }
}

if (! function_exists('submit_button')) {
    function submit_button(?string $text = null, string $type = 'primary', string $name = 'submit', bool $wrap = true, $other_attributes = null): void
    {
        $button = '<button type="submit" name="' . esc_attr($name) . '" class="button button-' . esc_attr($type) . '">'
            . esc_html($text ?? 'Save Changes') . '</button>';

        echo $wrap ? '<p class="submit">' . $button . '</p>' : $button;
    }
}

// -- Meta boxes (registration only) ---------------------------------------------

if (! function_exists('add_meta_box')) {
    function add_meta_box(string $id, string $title, $callback, $screen = null, string $context = 'advanced', string $priority = 'default', ?array $callback_args = null): void
    {
        // The LightCMS block editor does not host WP meta boxes. They are
        // recorded so `php spark wp:doctor` can report which custom fields
        // a theme expects, and so remove_meta_box() does not fatal.
        Registry::$shutdown['meta_boxes'][$id] = compact('id', 'title', 'callback', 'screen', 'context', 'priority');
    }
}

if (! function_exists('remove_meta_box')) {
    function remove_meta_box(string $id, $screen, string $context): void
    {
        unset(Registry::$shutdown['meta_boxes'][$id]);
    }
}

if (! function_exists('do_meta_boxes')) {
    function do_meta_boxes($screen, string $context, $data_object): int
    {
        return 0;
    }
}

// -- Nonces --------------------------------------------------------------------------

if (! function_exists('wp_nonce_tick')) {
    function wp_nonce_tick(): int
    {
        return (int) ceil(time() / (DAY_IN_SECONDS / 2));
    }
}

if (! function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1): string
    {
        $key  = config(Config\Encryption::class)->key ?: 'lightcms-wp-compat';
        $user = get_current_user_id();
        $tick = wp_nonce_tick();

        return substr(hash_hmac('sha256', $tick . '|' . $action . '|' . $user, (string) $key), -12, 10);
    }
}

if (! function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1)
    {
        $nonce = (string) $nonce;

        if ($nonce === '') {
            return false;
        }

        $key  = config(Config\Encryption::class)->key ?: 'lightcms-wp-compat';
        $user = get_current_user_id();
        $tick = wp_nonce_tick();

        foreach ([0 => 1, 1 => 2] as $offset => $result) {
            $expected = substr(hash_hmac('sha256', ($tick - $offset) . '|' . $action . '|' . $user, (string) $key), -12, 10);

            if (hash_equals($expected, $nonce)) {
                return $result;
            }
        }

        return false;
    }
}

if (! function_exists('wp_nonce_field')) {
    function wp_nonce_field($action = -1, string $name = '_wpnonce', bool $referer = true, bool $display = true)
    {
        $field = '<input type="hidden" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="' . esc_attr(wp_create_nonce($action)) . '">';

        if ($referer) {
            $field .= '<input type="hidden" name="_wp_http_referer" value="' . esc_attr(wp_current_url()) . '">';
        }

        if ($display) {
            echo $field;

            return null;
        }

        return $field;
    }
}

if (! function_exists('wp_nonce_url')) {
    function wp_nonce_url(string $actionurl, $action = -1, string $name = '_wpnonce'): string
    {
        return add_query_arg($name, wp_create_nonce($action), $actionurl);
    }
}

if (! function_exists('check_admin_referer')) {
    function check_admin_referer($action = -1, string $query_arg = '_wpnonce')
    {
        $nonce = $_REQUEST[$query_arg] ?? '';
        $valid = wp_verify_nonce($nonce, $action);

        if (! $valid) {
            wp_die('The link you followed has expired.', 'Security check failed', ['response' => 403]);
        }

        return $valid;
    }
}

if (! function_exists('check_ajax_referer')) {
    function check_ajax_referer($action = -1, $query_arg = false, bool $stop = true)
    {
        $nonce = $_REQUEST[$query_arg ?: '_ajax_nonce'] ?? ($_REQUEST['_wpnonce'] ?? '');
        $valid = wp_verify_nonce($nonce, $action);

        if (! $valid && $stop) {
            wp_die('Invalid nonce.', 'Security check failed', ['response' => 403]);
        }

        return $valid;
    }
}

// -- JSON responses -----------------------------------------------------------------------

if (! function_exists('wp_send_json')) {
    function wp_send_json($response, ?int $status_code = null, int $flags = 0): void
    {
        throw new App\Libraries\WordPress\Exceptions\WordPressJsonException($response, $status_code ?? 200, $flags);
    }
}

if (! function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null, ?int $status_code = null, int $flags = 0): void
    {
        wp_send_json(['success' => true, 'data' => $data], $status_code, $flags);
    }
}

if (! function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, ?int $status_code = null, int $flags = 0): void
    {
        wp_send_json(['success' => false, 'data' => $data], $status_code, $flags);
    }
}

// -- Screens / misc admin -------------------------------------------------------------------

if (! function_exists('get_current_screen')) {
    function get_current_screen()
    {
        return $GLOBALS['current_screen'] ?? null;
    }
}

if (! function_exists('set_current_screen')) {
    function set_current_screen($hook_name = ''): void
    {
        $GLOBALS['current_screen'] = (object) ['id' => (string) $hook_name, 'base' => (string) $hook_name, 'is_block_editor' => false];
    }
}

if (! function_exists('wp_get_admin_notices')) {
    function wp_get_admin_notices(): array
    {
        return Registry::$adminNotices;
    }
}

if (! function_exists('wp_add_admin_notice')) {
    function wp_add_admin_notice(string $message, string $type = 'info'): void
    {
        Registry::$adminNotices[] = ['type' => $type, 'message' => $message];
    }
}

if (! function_exists('wp_enqueue_editor')) {
    function wp_enqueue_editor(): void
    {
    }
}

if (! function_exists('flush_rewrite_rules')) {
    function flush_rewrite_rules(bool $hard = true): void
    {
        // LightCMS routes are static; nothing to flush. Defined so themes
        // that call it on activation do not fatal.
    }
}

if (! function_exists('add_rewrite_rule')) {
    function add_rewrite_rule(string $regex, $query, string $after = 'bottom'): void
    {
        log_message('info', "WP compat: add_rewrite_rule('{$regex}') ignored — add the route in app/Config/Routes.php instead.");
    }
}

if (! function_exists('add_rewrite_tag')) {
    function add_rewrite_tag(string $tag, string $regex, string $query = ''): void
    {
    }
}

if (! function_exists('get_query_var')) {
    function get_query_var(string $query_var, $default_value = '')
    {
        return $GLOBALS['wp_query']->query_vars[$query_var] ?? $default_value;
    }
}

if (! function_exists('set_query_var')) {
    function set_query_var(string $query_var, $value): void
    {
        if (isset($GLOBALS['wp_query'])) {
            $GLOBALS['wp_query']->query_vars[$query_var] = $value;
        }
    }
}
