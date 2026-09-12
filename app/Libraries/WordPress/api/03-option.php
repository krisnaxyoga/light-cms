<?php

/**
 * Options, theme mods, transients and the object cache.
 *
 * Transients map onto CodeIgniter's cache handler (file by default,
 * Redis/Memcached if configured), and wp_cache_* is a per-request array —
 * the same durability contract WordPress gives you without a persistent
 * object cache plugin.
 */

if (! function_exists('get_option')) {
    function get_option(string $option, $default_value = false)
    {
        $pre = apply_filters('pre_option_' . $option, false, $option, $default_value);

        if ($pre !== false) {
            return $pre;
        }

        $value = wp_option_store()->get($option, $default_value);

        return apply_filters('option_' . $option, $value, $option);
    }
}

if (! function_exists('add_option')) {
    function add_option(string $option, $value = '', string $deprecated = '', $autoload = 'yes'): bool
    {
        return wp_option_store()->add($option, $value, $autoload);
    }
}

if (! function_exists('update_option')) {
    function update_option(string $option, $value, $autoload = null): bool
    {
        $old   = wp_option_store()->get($option, false);
        $value = apply_filters('pre_update_option_' . $option, $value, $old, $option);

        $updated = wp_option_store()->update($option, $value, $autoload ?? true);

        if ($updated) {
            do_action('update_option_' . $option, $old, $value, $option);
            do_action('updated_option', $option, $old, $value);
        }

        return $updated;
    }
}

if (! function_exists('delete_option')) {
    function delete_option(string $option): bool
    {
        $deleted = wp_option_store()->delete($option);

        if ($deleted) {
            do_action('delete_option', $option);
        }

        return $deleted;
    }
}

if (! function_exists('get_site_option')) {
    function get_site_option(string $option, $default_value = false, bool $deprecated = true)
    {
        return get_option($option, $default_value);
    }
}

if (! function_exists('update_site_option')) {
    function update_site_option(string $option, $value): bool
    {
        return update_option($option, $value);
    }
}

if (! function_exists('add_site_option')) {
    function add_site_option(string $option, $value): bool
    {
        return add_option($option, $value);
    }
}

if (! function_exists('delete_site_option')) {
    function delete_site_option(string $option): bool
    {
        return delete_option($option);
    }
}

// -- Theme mods ---------------------------------------------------------------

if (! function_exists('get_theme_mods')) {
    function get_theme_mods(): array
    {
        $mods = get_option('theme_mods_' . get_stylesheet(), []);

        return is_array($mods) ? $mods : [];
    }
}

if (! function_exists('get_theme_mod')) {
    function get_theme_mod(string $name, $default_value = false)
    {
        $mods  = get_theme_mods();
        $value = array_key_exists($name, $mods) ? $mods[$name] : $default_value;

        return apply_filters('theme_mod_' . $name, $value);
    }
}

if (! function_exists('set_theme_mod')) {
    function set_theme_mod(string $name, $value): bool
    {
        $mods        = get_theme_mods();
        $old         = $mods[$name] ?? false;
        $mods[$name] = apply_filters('pre_set_theme_mod_' . $name, $value, $old);

        return update_option('theme_mods_' . get_stylesheet(), $mods);
    }
}

if (! function_exists('remove_theme_mod')) {
    function remove_theme_mod(string $name): void
    {
        $mods = get_theme_mods();
        unset($mods[$name]);
        update_option('theme_mods_' . get_stylesheet(), $mods);
    }
}

if (! function_exists('remove_theme_mods')) {
    function remove_theme_mods(): void
    {
        delete_option('theme_mods_' . get_stylesheet());
    }
}

// -- Transients -----------------------------------------------------------------

if (! function_exists('get_transient')) {
    function get_transient(string $transient)
    {
        $pre = apply_filters('pre_transient_' . $transient, false, $transient);

        if ($pre !== false) {
            return $pre;
        }

        $value = Config\Services::cache()->get('wp_transient_' . md5($transient));

        return apply_filters('transient_' . $transient, $value ?? false, $transient);
    }
}

if (! function_exists('set_transient')) {
    function set_transient(string $transient, $value, int $expiration = 0): bool
    {
        $value = apply_filters('pre_set_transient_' . $transient, $value, $expiration, $transient);

        // CI's cache treats 0 as "use the default TTL"; WP treats it as
        // "never expire", so map it to a long-but-finite window.
        $ttl = $expiration > 0 ? $expiration : YEAR_IN_SECONDS;

        $saved = Config\Services::cache()->save('wp_transient_' . md5($transient), $value, $ttl);

        if ($saved) {
            do_action('set_transient_' . $transient, $value, $expiration, $transient);
        }

        return (bool) $saved;
    }
}

if (! function_exists('delete_transient')) {
    function delete_transient(string $transient): bool
    {
        do_action('delete_transient_' . $transient, $transient);

        return (bool) Config\Services::cache()->delete('wp_transient_' . md5($transient));
    }
}

if (! function_exists('get_site_transient')) {
    function get_site_transient(string $transient)
    {
        return get_transient($transient);
    }
}

if (! function_exists('set_site_transient')) {
    function set_site_transient(string $transient, $value, int $expiration = 0): bool
    {
        return set_transient($transient, $value, $expiration);
    }
}

if (! function_exists('delete_site_transient')) {
    function delete_site_transient(string $transient): bool
    {
        return delete_transient($transient);
    }
}

// -- Object cache (per request) ----------------------------------------------------

if (! function_exists('wp_cache_get')) {
    function wp_cache_get($key, string $group = '', bool $force = false, &$found = null)
    {
        $store = &wp_object_cache_store();
        $found = isset($store[$group][$key]);

        return $found ? $store[$group][$key] : false;
    }
}

if (! function_exists('wp_object_cache_store')) {
    function &wp_object_cache_store(): array
    {
        static $store = [];

        return $store;
    }
}

if (! function_exists('wp_cache_set')) {
    function wp_cache_set($key, $data, string $group = '', int $expire = 0): bool
    {
        $store = &wp_object_cache_store();
        $store[$group][$key] = $data;

        return true;
    }
}

if (! function_exists('wp_cache_add')) {
    function wp_cache_add($key, $data, string $group = '', int $expire = 0): bool
    {
        $store = &wp_object_cache_store();

        if (isset($store[$group][$key])) {
            return false;
        }

        return wp_cache_set($key, $data, $group, $expire);
    }
}

if (! function_exists('wp_cache_replace')) {
    function wp_cache_replace($key, $data, string $group = '', int $expire = 0): bool
    {
        $store = &wp_object_cache_store();

        return isset($store[$group][$key]) ? wp_cache_set($key, $data, $group, $expire) : false;
    }
}

if (! function_exists('wp_cache_delete')) {
    function wp_cache_delete($key, string $group = ''): bool
    {
        $store = &wp_object_cache_store();

        if (! isset($store[$group][$key])) {
            return false;
        }

        unset($store[$group][$key]);

        return true;
    }
}

if (! function_exists('wp_cache_flush')) {
    function wp_cache_flush(): bool
    {
        $store = &wp_object_cache_store();
        $store = [];

        return true;
    }
}

if (! function_exists('wp_cache_add_global_groups')) {
    function wp_cache_add_global_groups($groups): void
    {
    }
}

if (! function_exists('wp_cache_add_non_persistent_groups')) {
    function wp_cache_add_non_persistent_groups($groups): void
    {
    }
}

if (! function_exists('wp_using_ext_object_cache')) {
    function wp_using_ext_object_cache($using = null): bool
    {
        return false;
    }
}
