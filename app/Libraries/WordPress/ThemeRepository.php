<?php

namespace App\Libraries\WordPress;

use App\Models\ThemeModel;
use Config\WordPress as WordPressConfig;

/**
 * Discovers WordPress themes in public/wp-content/themes and answers
 * "which one is active, and where does it live" for the rest of the
 * compat layer. Child themes (a Template: header) are resolved here:
 * the child directory is the stylesheet, the parent is the template,
 * and template lookups fall through child -> parent exactly as in WP.
 */
class ThemeRepository
{
    protected static ?array $activeRow = null;
    protected static bool $activeLoaded = false;

    public function __construct(protected ?WordPressConfig $config = null)
    {
        $this->config ??= config(WordPressConfig::class);
    }

    public function themesDir(): string
    {
        return rtrim(FCPATH . $this->config->themesPath, '/') . '/';
    }

    /**
     * @return array<string, array<string, string>> slug => header data + path/uri
     */
    public function available(): array
    {
        $themes = [];

        foreach (glob($this->themesDir() . '*', GLOB_ONLYDIR) ?: [] as $dir) {
            $slug  = basename($dir);
            $style = $dir . '/style.css';

            if (! is_file($style)) {
                continue; // not a WP theme directory
            }

            $header = Headers::parse($style, Headers::THEME);

            if ($header['Name'] === '') {
                continue;
            }

            $themes[$slug] = $header + [
                'slug'       => $slug,
                'path'       => $dir,
                'uri'        => rtrim(base_url($this->config->themesPath . $slug), '/'),
                'screenshot' => $this->screenshotUrl($dir, $slug),
                'is_child'   => $header['Template'] !== '',
            ];
        }

        ksort($themes);

        return $themes;
    }

    public function get(string $slug): ?array
    {
        return $this->available()[$slug] ?? null;
    }

    public function exists(string $slug): bool
    {
        return is_file($this->themesDir() . $slug . '/style.css');
    }

    /** Slug of the active WP theme (the stylesheet), or null if a native theme is active. */
    public function activeSlug(): ?string
    {
        $row = $this->activeRow();

        return $row === null ? null : $row['slug'];
    }

    /** Parent theme slug when the active theme is a child theme. */
    public function activeParentSlug(): ?string
    {
        $row = $this->activeRow();

        if ($row === null) {
            return null;
        }

        $parent = $row['parent_slug'] ?? '';

        if ($parent !== '' && $this->exists($parent)) {
            return $parent;
        }

        $header = $this->get($row['slug']);
        $parent = $header['Template'] ?? '';

        return ($parent !== '' && $this->exists($parent)) ? $parent : null;
    }

    /** True when the request should be rendered by the WordPress layer. */
    public function isActive(): bool
    {
        return $this->config->enabled && $this->activeRow() !== null;
    }

    /** Absolute path of the active child/stylesheet theme. */
    public function stylesheetPath(): string
    {
        return rtrim($this->themesDir() . (string) $this->activeSlug(), '/');
    }

    /** Absolute path of the parent/template theme (same as stylesheet when there is no parent). */
    public function templatePath(): string
    {
        $parent = $this->activeParentSlug();

        return rtrim($this->themesDir() . ($parent ?? (string) $this->activeSlug()), '/');
    }

    public function stylesheetUri(): string
    {
        return rtrim(base_url($this->config->themesPath . (string) $this->activeSlug()), '/');
    }

    public function templateUri(): string
    {
        $parent = $this->activeParentSlug() ?? (string) $this->activeSlug();

        return rtrim(base_url($this->config->themesPath . $parent), '/');
    }

    /**
     * Child-first file lookup. Returns the first existing absolute path
     * for $relative, or null.
     */
    public function locate(string $relative): ?string
    {
        $relative = ltrim($relative, '/');

        foreach ([$this->stylesheetPath(), $this->templatePath()] as $base) {
            if ($base !== '' && is_file($base . '/' . $relative)) {
                return $base . '/' . $relative;
            }
        }

        return null;
    }

    /**
     * Record a WP theme in the `themes` table and make it the active one.
     * Native LightCMS themes stay in the same table; `engine` tells the
     * front controller which renderer owns the request.
     */
    public function activate(string $slug): bool
    {
        $theme = $this->get($slug);

        if ($theme === null) {
            return false;
        }

        $model    = new ThemeModel();
        $existing = $model->where('slug', $slug)->first();

        $payload = [
            'name'        => $theme['Name'],
            'slug'        => $slug,
            'engine'      => 'wordpress',
            'parent_slug' => $theme['Template'] !== '' ? $theme['Template'] : null,
            'version'     => mb_substr($theme['Version'], 0, 20),
            'author'      => mb_substr(strip_tags($theme['Author']), 0, 100),
        ];

        if ($existing) {
            $model->update($existing['id'], $payload);
        } else {
            $model->insert($payload);
        }

        static::$activeLoaded = false;
        static::$activeRow    = null;

        return $model->activate($slug);
    }

    protected function activeRow(): ?array
    {
        if (static::$activeLoaded) {
            return static::$activeRow;
        }

        static::$activeLoaded = true;
        static::$activeRow    = null;

        try {
            $row = (new ThemeModel())->getActive();
        } catch (\Throwable $e) {
            // No DB yet (install/CLI before migrate) — behave as "not active".
            log_message('debug', 'WP compat: could not read active theme: ' . $e->getMessage());

            return null;
        }

        if ($row === null || ($row['engine'] ?? 'native') !== 'wordpress') {
            return null;
        }

        if (! $this->exists($row['slug'])) {
            log_message('warning', "WP compat: active theme '{$row['slug']}' is missing from disk.");

            return null;
        }

        return static::$activeRow = $row;
    }

    /** Test seam: forget the cached active-theme row. */
    public static function flush(): void
    {
        static::$activeLoaded = false;
        static::$activeRow    = null;
    }

    protected function screenshotUrl(string $dir, string $slug): string
    {
        foreach (['screenshot.png', 'screenshot.jpg', 'screenshot.gif'] as $name) {
            if (is_file($dir . '/' . $name)) {
                return base_url($this->config->themesPath . $slug . '/' . $name);
            }
        }

        return '';
    }
}
