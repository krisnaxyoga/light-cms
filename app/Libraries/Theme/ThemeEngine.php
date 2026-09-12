<?php

namespace App\Libraries\Theme;

use App\Models\ThemeModel;
use Config\LightCMS as LightCMSConfig;

/**
 * Loads the active theme, exposes its layouts/templates to controllers,
 * and renders the styles/scripts a theme's functions.php queued via the
 * Theme facade (PRD §3.3.A + §6.2 sample, adapted to a real queue/flush
 * model instead of eager echo).
 */
class ThemeEngine
{
    protected string $activeTheme;
    protected string $themePath;
    protected array $config = [];

    public function __construct(?ThemeModel $themeModel = null)
    {
        $themeModel        = $themeModel ?? new ThemeModel();
        $this->activeTheme = $this->resolveActiveTheme($themeModel);
        $this->themePath   = FCPATH . config(LightCMSConfig::class)->themesPath . $this->activeTheme;

        Theme::reset();
        $this->loadThemeConfig();
        $this->loadThemeFunctions();
    }

    public function activeThemeSlug(): string
    {
        return $this->activeTheme;
    }

    public function config(): array
    {
        return $this->config;
    }

    public function render(string $template, array $data = []): string
    {
        $templateFile = $this->themePath . '/templates/' . $template . '.php';

        if (! is_file($templateFile)) {
            throw new \RuntimeException("Template not found in theme '{$this->activeTheme}': {$template}");
        }

        return $this->includeWithData($templateFile, $data);
    }

    public function getHeader(array $data = []): string
    {
        return $this->loadLayout('header', $data);
    }

    public function getFooter(array $data = []): string
    {
        return $this->loadLayout('footer', $data);
    }

    public function getSidebar(array $data = []): string
    {
        return $this->loadLayout('sidebar', $data);
    }

    public function themeUrl(string $path = ''): string
    {
        $themesPath = config(LightCMSConfig::class)->themesPath;

        return rtrim(base_url($themesPath . $this->activeTheme . '/' . ltrim($path, '/')), '/');
    }

    public function renderStyles(): string
    {
        $html = [];

        foreach (Theme::styles() as $style) {
            $url    = $this->resolveAssetUrl($style['src']);
            $html[] = '<link rel="stylesheet" href="' . esc($url, 'attr') . '">';
        }

        return implode("\n", $html);
    }

    public function renderScripts(): string
    {
        $html = [];

        foreach (Theme::scripts() as $script) {
            $url    = $this->resolveAssetUrl($script['src']);
            $defer  = $script['defer'] ? ' defer' : '';
            $html[] = '<script src="' . esc($url, 'attr') . '"' . $defer . '></script>';
        }

        return implode("\n", $html);
    }

    protected function resolveAssetUrl(string $src): string
    {
        // Allow themes to enqueue absolute/CDN URLs as-is.
        if (preg_match('#^https?://#i', $src)) {
            return $src;
        }

        return $this->themeUrl($src);
    }

    protected function loadLayout(string $layout, array $data = []): string
    {
        $layoutFile = $this->themePath . '/layouts/' . $layout . '.php';

        if (! is_file($layoutFile)) {
            return '';
        }

        return $this->includeWithData($layoutFile, $data);
    }

    protected function includeWithData(string $file, array $data): string
    {
        extract($data);
        $engine = $this; // available to layouts/templates as $engine
        ob_start();
        include $file;

        return ob_get_clean();
    }

    protected function loadThemeConfig(): void
    {
        $configFile = $this->themePath . '/theme.json';

        if (is_file($configFile)) {
            $this->config = json_decode(file_get_contents($configFile), true) ?? [];
        }
    }

    protected function loadThemeFunctions(): void
    {
        $functionsFile = $this->themePath . '/functions.php';

        if (is_file($functionsFile)) {
            require $functionsFile;
        }
    }

    protected function resolveActiveTheme(ThemeModel $themeModel): string
    {
        $theme = $themeModel->getActive();
        $slug  = $theme['slug'] ?? config(LightCMSConfig::class)->defaultTheme;

        // Guard against a DB record pointing at a theme directory that
        // was deleted from disk — fall back rather than 500.
        $path = FCPATH . config(LightCMSConfig::class)->themesPath . $slug;

        return is_dir($path) ? $slug : config(LightCMSConfig::class)->defaultTheme;
    }
}
