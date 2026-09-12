<?php

/**
 * WP-Cron. Events are stored in the `cron` option the way WordPress does
 * it and executed by `php spark lightcms:cleanup` (or on-request when
 * Config\WordPress::$spawnCronOnRequest is on) — never by a hidden loopback
 * HTTP request.
 */

if (! function_exists('wp_get_schedules')) {
    function wp_get_schedules(): array
    {
        return apply_filters('cron_schedules', [
            'hourly'     => ['interval' => HOUR_IN_SECONDS, 'display' => 'Once Hourly'],
            'twicedaily' => ['interval' => 12 * HOUR_IN_SECONDS, 'display' => 'Twice Daily'],
            'daily'      => ['interval' => DAY_IN_SECONDS, 'display' => 'Once Daily'],
            'weekly'     => ['interval' => WEEK_IN_SECONDS, 'display' => 'Once Weekly'],
        ]);
    }
}

if (! function_exists('_get_cron_array')) {
    function _get_cron_array(): array
    {
        $cron = get_option('cron', []);

        return is_array($cron) ? $cron : [];
    }
}

if (! function_exists('_set_cron_array')) {
    function _set_cron_array(array $cron): bool
    {
        return update_option('cron', $cron);
    }
}

if (! function_exists('wp_schedule_event')) {
    function wp_schedule_event(int $timestamp, string $recurrence, string $hook, array $args = [], bool $wp_error = false)
    {
        if (! isset(wp_get_schedules()[$recurrence])) {
            return $wp_error ? new WP_Error('invalid_schedule', 'Unknown schedule: ' . $recurrence) : false;
        }

        $cron = _get_cron_array();
        $key  = md5(serialize($args));

        $cron[$timestamp][$hook][$key] = [
            'schedule' => $recurrence,
            'args'     => $args,
            'interval' => wp_get_schedules()[$recurrence]['interval'],
        ];

        ksort($cron, SORT_NUMERIC);

        return _set_cron_array($cron);
    }
}

if (! function_exists('wp_schedule_single_event')) {
    function wp_schedule_single_event(int $timestamp, string $hook, array $args = [], bool $wp_error = false)
    {
        $cron = _get_cron_array();
        $key  = md5(serialize($args));

        $cron[$timestamp][$hook][$key] = ['schedule' => false, 'args' => $args];
        ksort($cron, SORT_NUMERIC);

        return _set_cron_array($cron);
    }
}

if (! function_exists('wp_next_scheduled')) {
    function wp_next_scheduled(string $hook, array $args = [])
    {
        $key = md5(serialize($args));

        foreach (_get_cron_array() as $timestamp => $hooks) {
            if (isset($hooks[$hook][$key])) {
                return (int) $timestamp;
            }
        }

        return false;
    }
}

if (! function_exists('wp_unschedule_event')) {
    function wp_unschedule_event(int $timestamp, string $hook, array $args = [], bool $wp_error = false)
    {
        $cron = _get_cron_array();
        $key  = md5(serialize($args));

        unset($cron[$timestamp][$hook][$key]);

        if (($cron[$timestamp][$hook] ?? []) === []) {
            unset($cron[$timestamp][$hook]);
        }

        if (($cron[$timestamp] ?? []) === []) {
            unset($cron[$timestamp]);
        }

        return _set_cron_array($cron);
    }
}

if (! function_exists('wp_clear_scheduled_hook')) {
    function wp_clear_scheduled_hook(string $hook, array $args = [], bool $wp_error = false)
    {
        $cron    = _get_cron_array();
        $key     = md5(serialize($args));
        $cleared = 0;

        foreach ($cron as $timestamp => $hooks) {
            if (isset($hooks[$hook][$key])) {
                unset($cron[$timestamp][$hook][$key]);
                $cleared++;
            }

            if (($cron[$timestamp][$hook] ?? []) === []) {
                unset($cron[$timestamp][$hook]);
            }

            if (($cron[$timestamp] ?? []) === []) {
                unset($cron[$timestamp]);
            }
        }

        _set_cron_array($cron);

        return $cleared;
    }
}

if (! function_exists('wp_unschedule_hook')) {
    function wp_unschedule_hook(string $hook, bool $wp_error = false)
    {
        return wp_clear_scheduled_hook($hook);
    }
}

if (! function_exists('wp_get_scheduled_event')) {
    function wp_get_scheduled_event(string $hook, array $args = [], ?int $timestamp = null)
    {
        $next = $timestamp ?? wp_next_scheduled($hook, $args);

        if ($next === false) {
            return false;
        }

        $key   = md5(serialize($args));
        $event = _get_cron_array()[$next][$hook][$key] ?? null;

        return $event === null ? false : (object) (['hook' => $hook, 'timestamp' => $next] + $event);
    }
}

if (! function_exists('wp_cron_run_due')) {
    /**
     * Run every event whose time has come. Returns the hooks it fired.
     *
     * @return list<string>
     */
    function wp_cron_run_due(?int $now = null): array
    {
        $now  = $now ?? time();
        $cron = _get_cron_array();
        $fired = [];

        foreach ($cron as $timestamp => $hooks) {
            if ((int) $timestamp > $now) {
                break; // the array is sorted by timestamp
            }

            foreach ($hooks as $hook => $events) {
                foreach ($events as $key => $event) {
                    try {
                        do_action($hook, ...array_values($event['args'] ?? []));
                        $fired[] = $hook;
                    } catch (\Throwable $e) {
                        log_message('error', "WP compat: cron event {$hook} failed: " . $e->getMessage());
                    }

                    unset($cron[$timestamp][$hook][$key]);

                    if (! empty($event['schedule']) && ! empty($event['interval'])) {
                        $next = (int) $timestamp + (int) $event['interval'];
                        $cron[$next][$hook][$key] = $event;
                    }
                }

                if (($cron[$timestamp][$hook] ?? []) === []) {
                    unset($cron[$timestamp][$hook]);
                }
            }

            if (($cron[$timestamp] ?? []) === []) {
                unset($cron[$timestamp]);
            }
        }

        ksort($cron, SORT_NUMERIC);
        _set_cron_array($cron);

        return $fired;
    }
}

if (! function_exists('spawn_cron')) {
    function spawn_cron($gmt_time = 0): void
    {
        if (config(Config\WordPress::class)->spawnCronOnRequest) {
            wp_cron_run_due();
        }
    }
}

if (! function_exists('wp_doing_cron')) {
    function wp_doing_cron(): bool
    {
        return (bool) ($GLOBALS['lightcms_doing_cron'] ?? false);
    }
}

if (! function_exists('wp_doing_ajax')) {
    function wp_doing_ajax(): bool
    {
        return (bool) ($GLOBALS['lightcms_doing_ajax'] ?? false);
    }
}
