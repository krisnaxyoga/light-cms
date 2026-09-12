<?php

namespace App\Libraries\WordPress;

/**
 * The WordPress Plugin API (actions + filters), re-implemented.
 *
 * Callbacks are stored as $filters[tag][priority][id] = [callback, args]
 * exactly like WP's $wp_filter, so priority ordering, re-entrancy
 * (a filter adding/removing callbacks while it runs), and
 * current_filter()/doing_action() all behave the way theme and plugin
 * authors expect.
 */
class Hooks
{
    /** @var array<string, array<int, array<string, array{0: mixed, 1: int}>>> */
    protected static array $filters = [];

    /** @var array<string, int> */
    protected static array $actions = [];

    /** @var list<string> */
    protected static array $current = [];

    public static function reset(): void
    {
        static::$filters = [];
        static::$actions = [];
        static::$current = [];
    }

    public static function add(string $tag, mixed $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        static::$filters[$tag][$priority][static::id($callback)] = [$callback, $acceptedArgs];
        ksort(static::$filters[$tag], SORT_NUMERIC);

        return true;
    }

    public static function remove(string $tag, mixed $callback, int $priority = 10): bool
    {
        $id = static::id($callback);

        if (! isset(static::$filters[$tag][$priority][$id])) {
            return false;
        }

        unset(static::$filters[$tag][$priority][$id]);

        if (static::$filters[$tag][$priority] === []) {
            unset(static::$filters[$tag][$priority]);
        }

        return true;
    }

    public static function removeAll(string $tag, int|false $priority = false): void
    {
        if ($priority === false) {
            unset(static::$filters[$tag]);

            return;
        }

        unset(static::$filters[$tag][$priority]);
    }

    /**
     * @return int|false Priority of the callback, or false. When $callback
     *                   is false: whether anything at all is hooked.
     */
    public static function has(string $tag, mixed $callback = false): int|bool
    {
        if (! isset(static::$filters[$tag]) || static::$filters[$tag] === []) {
            return false;
        }

        if ($callback === false) {
            return true;
        }

        $id = static::id($callback);

        foreach (static::$filters[$tag] as $priority => $callbacks) {
            if (isset($callbacks[$id])) {
                return $priority;
            }
        }

        return false;
    }

    /**
     * @param list<mixed> $args Extra arguments after the filtered value.
     */
    public static function applyFilters(string $tag, mixed $value, array $args = []): mixed
    {
        static::$current[] = $tag;
        static::$actions[$tag] = (static::$actions[$tag] ?? 0) + 1;

        // Snapshot per priority level, but re-read the tag each pass so a
        // callback that hooks a *later* priority still gets to run.
        $done = [];

        while (true) {
            $next = null;

            foreach (array_keys(static::$filters[$tag] ?? []) as $priority) {
                if (! in_array($priority, $done, true) && ($next === null || $priority < $next)) {
                    $next = $priority;
                }
            }

            if ($next === null) {
                break;
            }

            $done[] = $next;

            foreach (static::$filters[$tag][$next] ?? [] as $entry) {
                [$callback, $acceptedArgs] = $entry;

                if (! is_callable($callback)) {
                    continue;
                }

                $callArgs = array_merge([$value], $args);
                $callArgs = $acceptedArgs === 0 ? [] : array_slice($callArgs, 0, $acceptedArgs);
                $value    = $callback(...$callArgs);
            }
        }

        array_pop(static::$current);

        return $value;
    }

    /**
     * @param list<mixed> $args
     */
    public static function doAction(string $tag, array $args = []): void
    {
        static::$current[] = $tag;
        static::$actions[$tag] = (static::$actions[$tag] ?? 0) + 1;

        $done = [];

        while (true) {
            $next = null;

            foreach (array_keys(static::$filters[$tag] ?? []) as $priority) {
                if (! in_array($priority, $done, true) && ($next === null || $priority < $next)) {
                    $next = $priority;
                }
            }

            if ($next === null) {
                break;
            }

            $done[] = $next;

            foreach (static::$filters[$tag][$next] ?? [] as $entry) {
                [$callback, $acceptedArgs] = $entry;

                if (! is_callable($callback)) {
                    continue;
                }

                $callback(...array_slice($args, 0, $acceptedArgs));
            }
        }

        array_pop(static::$current);
    }

    public static function didAction(string $tag): int
    {
        return static::$actions[$tag] ?? 0;
    }

    public static function doing(?string $tag = null): bool
    {
        if ($tag === null) {
            return static::$current !== [];
        }

        return in_array($tag, static::$current, true);
    }

    public static function current(): string|false
    {
        return end(static::$current) ?: false;
    }

    /**
     * WP's _wp_filter_build_unique_id(): a stable string key for any
     * callable so the same callback can be removed later.
     */
    public static function id(mixed $callback): string
    {
        if (is_string($callback)) {
            return $callback;
        }

        if (is_object($callback)) {
            // Closure or invokable object.
            return spl_object_hash($callback);
        }

        if (is_array($callback)) {
            $target = $callback[0];
            $method = (string) ($callback[1] ?? '');

            if (is_object($target)) {
                return spl_object_hash($target) . $method;
            }

            return ((string) $target) . '::' . $method;
        }

        return (string) crc32(serialize($callback));
    }

    /** Debug helper used by `php spark wp:hooks`. */
    public static function all(): array
    {
        return static::$filters;
    }
}
