<?php

namespace App\Libraries\WordPress;

use Config\WordPress as WordPressConfig;
use Throwable;

/**
 * Boots the WordPress compatibility layer.
 *
 * Boot order mirrors WordPress itself, because plugins and themes depend
 * on it: constants -> API functions -> $wpdb/globals -> active plugins ->
 * plugins_loaded -> theme functions.php -> after_setup_theme -> init ->
 * wp_loaded. Everything is idempotent, so a controller, a CLI command
 * and a test can each call boot() without double-loading anything.
 */
class Runtime
{
    protected static ?self $instance = null;

    public const LEVEL_NONE    = 0;
    public const LEVEL_CORE    = 1;
    public const LEVEL_PLUGINS = 2;
    public const LEVEL_THEME   = 3;

    protected int $level = self::LEVEL_NONE;

    protected OptionStore $options;
    protected MetaStore $meta;
    protected Assets $assets;
    protected ThemeRepository $themes;
    protected PluginRepository $plugins;
    protected WordPressConfig $config;

    /** @var list<array{plugin: string, error: string}> */
    protected array $pluginFailures = [];

    protected bool $adminBooted = false;

    protected function __construct()
    {
        $this->config  = config(WordPressConfig::class);
        $this->options = new OptionStore();
        $this->meta    = new MetaStore();
        $this->assets  = new Assets();
        $this->themes  = new ThemeRepository($this->config);
        $this->plugins = new PluginRepository($this->config, $this->options);
    }

    public static function instance(): self
    {
        return static::$instance ??= new self();
    }

    /** Test seam — drops all registrations and re-runs boot from scratch. */
    public static function reset(): void
    {
        Hooks::reset();
        Registry::reset();
        Bridge\PostMapper::flush();
        Bridge\UserMapper::flush();
        ThemeRepository::flush();
        static::$instance = null;
    }

    public function options(): OptionStore
    {
        return $this->options;
    }

    public function meta(): MetaStore
    {
        return $this->meta;
    }

    public function assets(): Assets
    {
        return $this->assets;
    }

    public function themes(): ThemeRepository
    {
        return $this->themes;
    }

    public function plugins(): PluginRepository
    {
        return $this->plugins;
    }

    public function config(): WordPressConfig
    {
        return $this->config;
    }

    public function level(): int
    {
        return $this->level;
    }

    /** @return list<array{plugin: string, error: string}> */
    public function pluginFailures(): array
    {
        return $this->pluginFailures;
    }

    /**
     * Load constants + the API surface only. Safe to call anywhere
     * (including when a native LightCMS theme is active) — it defines
     * functions, it does not run third-party code.
     */
    public function bootCore(): self
    {
        if ($this->level >= self::LEVEL_CORE) {
            return $this;
        }

        $this->defineConstants();
        $this->loadFiles(__DIR__ . '/Stubs');
        $this->loadFiles(__DIR__ . '/api');
        $this->initGlobals();
        $this->registerDefaultFilters();

        $this->level = self::LEVEL_CORE;

        return $this;
    }

    /**
     * Core + every active plugin. Used by the admin (so plugin admin
     * pages/AJAX exist even under a native theme) and by the frontend.
     */
    public function bootPlugins(): self
    {
        $this->bootCore();

        if ($this->level >= self::LEVEL_PLUGINS) {
            return $this;
        }

        $this->level = self::LEVEL_PLUGINS; // set first: a plugin calling back in must not recurse

        $this->pluginFailures = $this->plugins->loadActive();

        foreach ($this->pluginFailures as $failure) {
            Registry::$adminNotices[] = [
                'type'    => 'error',
                'message' => "Plugin {$failure['plugin']} was deactivated after an error: {$failure['error']}",
            ];
        }

        Hooks::doAction('plugins_loaded');

        return $this;
    }

    /**
     * Full boot: plugins, then the active WP theme's functions.php, then
     * after_setup_theme/init/wp_loaded. Returns early (core only) when the
     * active theme is a native LightCMS theme.
     */
    public function boot(): self
    {
        $this->bootPlugins();

        if ($this->level >= self::LEVEL_THEME) {
            return $this;
        }

        if (! $this->themes->isActive()) {
            return $this;
        }

        $this->level = self::LEVEL_THEME;

        Hooks::doAction('setup_theme');
        $this->loadThemeFunctions();
        Hooks::doAction('after_setup_theme');
        Hooks::doAction('init');
        Hooks::doAction('widgets_init');
        Hooks::doAction('wp_loaded');

        return $this;
    }

    /**
     * Boot for an admin request: full boot plus the admin-only hooks
     * (`admin_init`, `admin_menu`) that plugins hang their settings
     * screens on. is_admin() answers true from here on.
     */
    public function bootAdmin(): self
    {
        $GLOBALS['lightcms_is_admin'] = true;

        $this->boot();

        if ($this->adminBooted) {
            return $this;
        }

        $this->adminBooted = true;

        Hooks::doAction('admin_init');
        Hooks::doAction('admin_menu');

        return $this;
    }

    /**
     * A child theme's functions.php loads *before* the parent's, which is
     * what lets a child remove_action() something the parent registers.
     */
    protected function loadThemeFunctions(): void
    {
        $stylesheet = $this->themes->stylesheetPath();
        $template   = $this->themes->templatePath();

        $files = $stylesheet === $template
            ? [$template . '/functions.php']
            : [$stylesheet . '/functions.php', $template . '/functions.php'];

        foreach ($files as $file) {
            if (! is_file($file)) {
                continue;
            }

            try {
                wp_include_file($file);
            } catch (Throwable $e) {
                log_message('critical', 'WP compat: theme functions.php failed: ' . $e->getMessage());
                Registry::$adminNotices[] = [
                    'type'    => 'error',
                    'message' => 'Theme functions.php error: ' . $e->getMessage(),
                ];
            }
        }
    }

    /**
     * WordPress's default-filters.php, trimmed to what matters here:
     * paragraph wrapping and shortcode expansion on the_content, so a
     * theme calling the_content() gets the same output shape as on WP.
     */
    protected function registerDefaultFilters(): void
    {
        Hooks::add('the_content', 'wptexturize', 10, 1);
        Hooks::add('the_content', 'wpautop', 10, 1);
        Hooks::add('the_content', 'shortcode_unautop', 10, 1);
        Hooks::add('the_content', 'do_shortcode', 11, 1);

        Hooks::add('the_excerpt', 'wptexturize', 10, 1);
        Hooks::add('the_excerpt', 'wpautop', 10, 1);
        Hooks::add('the_excerpt', 'shortcode_unautop', 10, 1);

        Hooks::add('the_title', 'wptexturize', 10, 1);
        Hooks::add('widget_text_content', 'do_shortcode', 11, 1);
        Hooks::add('term_description', 'wpautop', 10, 1);
    }

    protected function defineConstants(): void
    {
        $content    = rtrim(FCPATH . $this->config->contentPath, '/');
        $contentUrl = rtrim(base_url($this->config->contentPath), '/');

        $constants = [
            'ABSPATH'          => rtrim(FCPATH, '/\\') . '/',
            'WPINC'            => 'wp-includes',
            'WP_CONTENT_DIR'   => $content,
            'WP_CONTENT_URL'   => $contentUrl,
            'WP_PLUGIN_DIR'    => rtrim(FCPATH . $this->config->pluginsPath, '/'),
            'WP_PLUGIN_URL'    => rtrim(base_url($this->config->pluginsPath), '/'),
            'WPMU_PLUGIN_DIR'  => $content . '/mu-plugins',
            'WPMU_PLUGIN_URL'  => $contentUrl . '/mu-plugins',
            'WP_LANG_DIR'      => $content . '/languages',
            'WP_DEBUG'         => ENVIRONMENT !== 'production',
            'WP_DEBUG_LOG'     => false,
            'WP_DEBUG_DISPLAY' => ENVIRONMENT !== 'production',
            'SCRIPT_DEBUG'     => false,
            'WP_MEMORY_LIMIT'  => '64M',
            'EMPTY_TRASH_DAYS' => 30,
            'AUTOSAVE_INTERVAL'=> 60,
            'MINUTE_IN_SECONDS'=> 60,
            'HOUR_IN_SECONDS'  => 3600,
            'DAY_IN_SECONDS'   => 86400,
            'WEEK_IN_SECONDS'  => 604800,
            'MONTH_IN_SECONDS' => 2592000,
            'YEAR_IN_SECONDS'  => 31536000,
            'KB_IN_BYTES'      => 1024,
            'MB_IN_BYTES'      => 1048576,
            'GB_IN_BYTES'      => 1073741824,
            'LIGHTCMS_WP_COMPAT' => true,
        ];

        foreach ($constants as $name => $value) {
            defined($name) || define($name, $value);
        }
    }

    protected function initGlobals(): void
    {
        $GLOBALS['wpdb']               ??= new \wpdb();
        $GLOBALS['wp_version']         ??= $this->config->emulatedVersion;
        $GLOBALS['wp_widget_factory']  ??= new \WP_Widget_Factory();
        $GLOBALS['wp_registered_sidebars'] ??= [];
        $GLOBALS['wp_registered_widgets']  ??= [];
        $GLOBALS['post']               ??= null;
        $GLOBALS['wp_query']           ??= new \WP_Query();
        $GLOBALS['wp_the_query']       ??= $GLOBALS['wp_query'];
        $GLOBALS['shortcode_tags']     ??= [];
    }

    protected function loadFiles(string $dir): void
    {
        $files = glob($dir . '/*.php') ?: [];
        sort($files);

        foreach ($files as $file) {
            require_once $file;
        }
    }
}
