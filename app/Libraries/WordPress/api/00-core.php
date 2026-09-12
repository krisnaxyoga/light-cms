<?php

use App\Libraries\WordPress\Assets;
use App\Libraries\WordPress\Hooks;
use App\Libraries\WordPress\MetaStore;
use App\Libraries\WordPress\OptionStore;
use App\Libraries\WordPress\PluginRepository;
use App\Libraries\WordPress\Runtime;
use App\Libraries\WordPress\ThemeRepository;

/**
 * Service accessors + the WordPress Plugin API (actions and filters).
 * Everything else in api/ builds on these.
 */

if (! function_exists('wp_runtime')) {
    function wp_runtime(): Runtime
    {
        return Runtime::instance();
    }
}

if (! function_exists('wp_option_store')) {
    function wp_option_store(): OptionStore
    {
        return Runtime::instance()->options();
    }
}

if (! function_exists('wp_meta_store')) {
    function wp_meta_store(): MetaStore
    {
        return Runtime::instance()->meta();
    }
}

if (! function_exists('wp_assets')) {
    function wp_assets(): Assets
    {
        return Runtime::instance()->assets();
    }
}

if (! function_exists('wp_themes')) {
    function wp_themes(): ThemeRepository
    {
        return Runtime::instance()->themes();
    }
}

if (! function_exists('wp_plugins')) {
    function wp_plugins(): PluginRepository
    {
        return Runtime::instance()->plugins();
    }
}

if (! function_exists('wp_include_file')) {
    /**
     * Include a theme/plugin file at global scope.
     *
     * Third-party code assumes `$variable = 1;` at the top level of a file
     * creates a *global*, and that `global $post;` reaches the same one, so
     * the include cannot happen inside a class method's scope.
     */
    function wp_include_file(string $file, array $extract = []): mixed
    {
        if (! is_file($file)) {
            return false;
        }

        extract($extract, EXTR_SKIP);

        return require $file;
    }
}

// -- Actions and filters ---------------------------------------------------

if (! function_exists('add_filter')) {
    function add_filter(string $hook_name, $callback, int $priority = 10, int $accepted_args = 1): bool
    {
        return Hooks::add($hook_name, $callback, $priority, $accepted_args);
    }
}

if (! function_exists('add_action')) {
    function add_action(string $hook_name, $callback, int $priority = 10, int $accepted_args = 1): bool
    {
        return Hooks::add($hook_name, $callback, $priority, $accepted_args);
    }
}

if (! function_exists('apply_filters')) {
    function apply_filters(string $hook_name, $value, ...$args)
    {
        return Hooks::applyFilters($hook_name, $value, $args);
    }
}

if (! function_exists('apply_filters_ref_array')) {
    function apply_filters_ref_array(string $hook_name, array $args)
    {
        $value = array_shift($args);

        return Hooks::applyFilters($hook_name, $value, $args);
    }
}

if (! function_exists('do_action')) {
    function do_action(string $hook_name, ...$args): void
    {
        Hooks::doAction($hook_name, $args);
    }
}

if (! function_exists('do_action_ref_array')) {
    function do_action_ref_array(string $hook_name, array $args): void
    {
        Hooks::doAction($hook_name, $args);
    }
}

if (! function_exists('remove_filter')) {
    function remove_filter(string $hook_name, $callback, int $priority = 10): bool
    {
        return Hooks::remove($hook_name, $callback, $priority);
    }
}

if (! function_exists('remove_action')) {
    function remove_action(string $hook_name, $callback, int $priority = 10): bool
    {
        return Hooks::remove($hook_name, $callback, $priority);
    }
}

if (! function_exists('remove_all_filters')) {
    function remove_all_filters(string $hook_name, $priority = false): bool
    {
        Hooks::removeAll($hook_name, $priority === false ? false : (int) $priority);

        return true;
    }
}

if (! function_exists('remove_all_actions')) {
    function remove_all_actions(string $hook_name, $priority = false): bool
    {
        return remove_all_filters($hook_name, $priority);
    }
}

if (! function_exists('has_filter')) {
    function has_filter(string $hook_name, $callback = false)
    {
        return Hooks::has($hook_name, $callback);
    }
}

if (! function_exists('has_action')) {
    function has_action(string $hook_name, $callback = false)
    {
        return Hooks::has($hook_name, $callback);
    }
}

if (! function_exists('did_action')) {
    function did_action(string $hook_name): int
    {
        return Hooks::didAction($hook_name);
    }
}

if (! function_exists('doing_action')) {
    function doing_action(?string $hook_name = null): bool
    {
        return Hooks::doing($hook_name);
    }
}

if (! function_exists('doing_filter')) {
    function doing_filter(?string $hook_name = null): bool
    {
        return Hooks::doing($hook_name);
    }
}

if (! function_exists('current_filter')) {
    function current_filter()
    {
        return Hooks::current();
    }
}

if (! function_exists('current_action')) {
    function current_action()
    {
        return Hooks::current();
    }
}

// -- Plugin paths and lifecycle -------------------------------------------

if (! function_exists('plugin_dir_path')) {
    function plugin_dir_path(string $file): string
    {
        return rtrim(str_replace('\\', '/', dirname($file)), '/') . '/';
    }
}

if (! function_exists('plugin_basename')) {
    function plugin_basename(string $file): string
    {
        $file    = str_replace('\\', '/', $file);
        $plugins = str_replace('\\', '/', rtrim(WP_PLUGIN_DIR, '/')) . '/';

        if (str_starts_with($file, $plugins)) {
            return substr($file, strlen($plugins));
        }

        return basename($file);
    }
}

if (! function_exists('plugins_url')) {
    function plugins_url(string $path = '', string $plugin = ''): string
    {
        $url = rtrim(WP_PLUGIN_URL, '/');

        if ($plugin !== '') {
            $folder = dirname(plugin_basename($plugin));

            if ($folder !== '.' && $folder !== '') {
                $url .= '/' . $folder;
            }
        }

        return $path === '' ? $url : $url . '/' . ltrim($path, '/');
    }
}

if (! function_exists('plugin_dir_url')) {
    function plugin_dir_url(string $file): string
    {
        return trailingslashit(plugins_url('', $file));
    }
}

if (! function_exists('register_activation_hook')) {
    function register_activation_hook(string $file, $callback): void
    {
        add_action('activate_' . plugin_basename($file), $callback);
    }
}

if (! function_exists('register_deactivation_hook')) {
    function register_deactivation_hook(string $file, $callback): void
    {
        add_action('deactivate_' . plugin_basename($file), $callback);
    }
}

if (! function_exists('register_uninstall_hook')) {
    function register_uninstall_hook(string $file, $callback): void
    {
        add_action('uninstall_' . plugin_basename($file), $callback);
    }
}

if (! function_exists('is_plugin_active')) {
    function is_plugin_active(string $plugin): bool
    {
        return wp_plugins()->isActive($plugin);
    }
}

if (! function_exists('is_plugin_inactive')) {
    function is_plugin_inactive(string $plugin): bool
    {
        return ! is_plugin_active($plugin);
    }
}

if (! function_exists('get_plugins')) {
    function get_plugins(): array
    {
        return wp_plugins()->available();
    }
}

if (! function_exists('get_plugin_data')) {
    function get_plugin_data(string $file, bool $markup = true, bool $translate = true): array
    {
        return App\Libraries\WordPress\Headers::parse($file, App\Libraries\WordPress\Headers::PLUGIN);
    }
}

if (! function_exists('activate_plugin')) {
    function activate_plugin(string $plugin)
    {
        $result = wp_plugins()->activate($plugin);

        return $result === true ? null : new WP_Error('plugin_activation_failed', $result);
    }
}

if (! function_exists('deactivate_plugins')) {
    function deactivate_plugins($plugins, bool $silent = false): void
    {
        foreach ((array) $plugins as $plugin) {
            wp_plugins()->deactivate($plugin);
        }
    }
}

// -- Error / notice plumbing ------------------------------------------------

if (! function_exists('is_wp_error')) {
    function is_wp_error($thing): bool
    {
        return $thing instanceof WP_Error;
    }
}

if (! function_exists('wp_die')) {
    function wp_die($message = '', $title = '', $args = []): void
    {
        if ($message instanceof WP_Error) {
            $message = $message->get_error_message();
        }

        $status = is_array($args) ? (int) ($args['response'] ?? 500) : (int) $args;
        $status = $status >= 100 ? $status : 500;

        throw new App\Libraries\WordPress\Exceptions\WordPressDieException(
            is_scalar($message) ? (string) $message : 'Stopped.',
            $status,
            is_scalar($title) ? (string) $title : ''
        );
    }
}

if (! function_exists('_doing_it_wrong')) {
    function _doing_it_wrong(string $function_name, string $message, string $version = ''): void
    {
        log_message('warning', "WP compat: {$function_name} called incorrectly: {$message}");
    }
}

if (! function_exists('_deprecated_function')) {
    function _deprecated_function(string $function_name, string $version, string $replacement = ''): void
    {
        log_message('info', "WP compat: {$function_name} is deprecated" . ($replacement !== '' ? ", use {$replacement}" : ''));
    }
}

if (! function_exists('_deprecated_argument')) {
    function _deprecated_argument(string $function_name, string $version, string $message = ''): void
    {
        log_message('info', "WP compat: deprecated argument for {$function_name}: {$message}");
    }
}

if (! function_exists('_deprecated_hook')) {
    function _deprecated_hook(string $hook, string $version, string $replacement = '', string $message = ''): void
    {
        log_message('info', "WP compat: deprecated hook {$hook}");
    }
}
