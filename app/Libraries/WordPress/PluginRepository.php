<?php

namespace App\Libraries\WordPress;

use Config\WordPress as WordPressConfig;
use Throwable;

/**
 * Discovery, activation and loading of WordPress plugins living in
 * public/wp-content/plugins.
 *
 * Active plugins are stored in the `active_plugins` option as
 * "folder/file.php" paths — the same format WordPress uses, so an
 * imported wp_options row Just Works. Activation runs the plugin file
 * once inside a try/catch: a plugin that fatals is deactivated again and
 * reported rather than taking the whole site down.
 */
class PluginRepository
{
    public const OPTION = 'active_plugins';

    protected array $loaded = [];

    public function __construct(
        protected ?WordPressConfig $config = null,
        protected ?OptionStore $options = null,
    ) {
        $this->config ??= config(WordPressConfig::class);
        $this->options ??= new OptionStore();
    }

    public function pluginsDir(): string
    {
        return rtrim(FCPATH . $this->config->pluginsPath, '/') . '/';
    }

    /**
     * All plugins on disk, keyed by "folder/file.php" (or "file.php" for
     * a single-file plugin like hello.php).
     *
     * @return array<string, array<string, mixed>>
     */
    public function available(): array
    {
        $dir     = $this->pluginsDir();
        $plugins = [];

        if (! is_dir($dir)) {
            return $plugins;
        }

        // Single-file plugins in the plugins root.
        foreach (glob($dir . '*.php') ?: [] as $file) {
            $this->collect($plugins, $file, basename($file));
        }

        // Folder plugins: only the top level of each folder is scanned,
        // like WP — the bootstrap file always lives there.
        foreach (glob($dir . '*', GLOB_ONLYDIR) ?: [] as $folder) {
            foreach (glob($folder . '/*.php') ?: [] as $file) {
                $this->collect($plugins, $file, basename($folder) . '/' . basename($file));
            }
        }

        uasort($plugins, static fn ($a, $b) => strcasecmp($a['Name'], $b['Name']));

        return $plugins;
    }

    public function get(string $plugin): ?array
    {
        return $this->available()[$plugin] ?? null;
    }

    /** @return list<string> */
    public function active(): array
    {
        $active = $this->options->get(self::OPTION, []);

        return is_array($active) ? array_values(array_filter($active, 'is_string')) : [];
    }

    public function isActive(string $plugin): bool
    {
        return in_array($plugin, $this->active(), true);
    }

    public function path(string $plugin): string
    {
        return $this->pluginsDir() . ltrim($plugin, '/');
    }

    /**
     * Activate a plugin: load it now (so a syntax/fatal error surfaces
     * immediately, WP-style) and fire its activation hook.
     *
     * @return true|string true on success, otherwise the error message.
     */
    public function activate(string $plugin): true|string
    {
        if ($this->get($plugin) === null) {
            return "Plugin file not found: {$plugin}";
        }

        if ($this->isActive($plugin)) {
            return true;
        }

        try {
            $this->load($plugin);
            Hooks::doAction('activate_' . $plugin, [false]);
        } catch (Throwable $e) {
            return $e->getMessage();
        }

        $active   = $this->active();
        $active[] = $plugin;
        $this->options->update(self::OPTION, array_values(array_unique($active)));

        Hooks::doAction('activated_plugin', [$plugin, false]);

        return true;
    }

    public function deactivate(string $plugin): bool
    {
        if (! $this->isActive($plugin)) {
            return false;
        }

        try {
            Hooks::doAction('deactivate_' . $plugin, [false]);
        } catch (Throwable $e) {
            log_message('error', "WP compat: deactivation hook for {$plugin} failed: " . $e->getMessage());
        }

        $this->options->update(self::OPTION, array_values(array_diff($this->active(), [$plugin])));
        Hooks::doAction('deactivated_plugin', [$plugin, false]);

        return true;
    }

    /**
     * Include every active plugin. A plugin that throws is deactivated so
     * the next request boots cleanly, and the failure is logged and
     * surfaced as an admin notice instead of a white screen.
     *
     * @return list<array{plugin: string, error: string}>
     */
    public function loadActive(): array
    {
        $failures = [];

        foreach ($this->active() as $plugin) {
            if (! is_file($this->path($plugin))) {
                $failures[] = ['plugin' => $plugin, 'error' => 'File is missing from wp-content/plugins.'];
                continue;
            }

            try {
                $this->load($plugin);
            } catch (Throwable $e) {
                $failures[] = ['plugin' => $plugin, 'error' => $e->getMessage()];
                log_message('error', "WP compat: plugin {$plugin} failed to load: " . $e->getMessage());
                $this->options->update(self::OPTION, array_values(array_diff($this->active(), [$plugin])));
            }
        }

        return $failures;
    }

    /** Include one plugin file exactly once, in the global scope. */
    public function load(string $plugin): void
    {
        $file = $this->path($plugin);

        if (isset($this->loaded[$file])) {
            return;
        }

        $this->loaded[$file] = true;

        wp_include_file($file);
    }

    protected function collect(array &$plugins, string $file, string $key): void
    {
        $header = Headers::parse($file, Headers::PLUGIN);

        if ($header['Name'] === '') {
            return;
        }

        $plugins[$key] = $header + [
            'plugin' => $key,
            'file'   => $file,
            'dir'    => dirname($file),
        ];
    }
}
