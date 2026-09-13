<?php

use Config\Services;

if (! function_exists('theme_engine')) {
    function theme_engine(): \App\Libraries\Theme\ThemeEngine
    {
        return Services::themeEngine();
    }
}

if (! function_exists('theme_header')) {
    function theme_header(array $data = []): void
    {
        echo theme_engine()->getHeader($data);
    }
}

if (! function_exists('theme_footer')) {
    function theme_footer(array $data = []): void
    {
        echo theme_engine()->getFooter($data);
    }
}

if (! function_exists('theme_sidebar')) {
    function theme_sidebar(array $data = []): void
    {
        echo theme_engine()->getSidebar($data);
    }
}

if (! function_exists('theme_render')) {
    function theme_render(string $template, array $data = []): void
    {
        echo theme_engine()->render($template, $data);
    }
}

if (! function_exists('theme_url')) {
    function theme_url(string $path = ''): string
    {
        return theme_engine()->themeUrl($path);
    }
}

if (! function_exists('theme_styles')) {
    function theme_styles(): void
    {
        echo theme_engine()->renderStyles();
    }
}

if (! function_exists('theme_scripts')) {
    function theme_scripts(): void
    {
        echo theme_engine()->renderScripts();
    }
}

if (! function_exists('theme_option')) {
    /**
     * Read a value out of the active theme's `settings` JSON column
     * (populated from theme.json defaults + the Customizer, PRD §3.3.B).
     */
    function theme_option(string $key, mixed $default = null): mixed
    {
        static $settings = null;

        if ($settings === null) {
            $theme    = (new \App\Models\ThemeModel())->getActive();
            $settings = $theme['settings'] ?? [];
        }

        return $settings[$key] ?? $default;
    }
}

if (! function_exists('site_setting')) {
    /**
     * Read a global, site-wide setting (settings table) — not to be
     * confused with theme_option(), which reads the active theme's own
     * `settings` JSON column (per-theme Customizer values).
     */
    function site_setting(string $key, mixed $default = null): mixed
    {
        static $settings = null;

        if ($settings === null) {
            $settings = (new \App\Models\SettingModel())->getAutoloaded();
        }

        return $settings[$key] ?? $default;
    }
}

if (! function_exists('current_locale')) {
    /** Language code of the current frontend request (Admin -> Languages). */
    function current_locale(): string
    {
        return Services::locale()->current();
    }
}

if (! function_exists('locale_url')) {
    /**
     * Drop-in for site_url()/base_url() when linking to content: prefixes
     * the path with the language's URL prefix (none for the default
     * language). $locale defaults to the current request language.
     */
    function locale_url(string $path = '', ?string $locale = null): string
    {
        return Services::locale()->url($path, $locale);
    }
}

if (! function_exists('post_path')) {
    /**
     * Site-relative path (no domain) for a post/page's canonical URL:
     * a real blog post (post_type 'post', with a slug) lives under
     * "blog/{slug}"; anything else (pages, and any synthetic "post-like"
     * array a controller builds for the homepage/category archive's SEO
     * tags, which never sets post_type) keeps the bare slug it always
     * had. Locale-prefixed on top of that as usual (locale_url()).
     *
     * Every internal link to a post/page — templates, sitemap, hreflang,
     * canonical, schema.org, the admin "View on site" link — should go
     * through this (or post_url()) rather than building the path from
     * $post['slug'] directly, so the /blog/ prefix stays consistent
     * everywhere it needs to apply and nowhere it doesn't.
     */
    function post_path(array $post, ?string $locale = null): string
    {
        $slug = $post['slug'] ?? '';
        $path = ($slug !== '' && ($post['post_type'] ?? '') === 'post') ? 'blog/' . $slug : $slug;

        return ltrim(Services::locale()->path($path, $locale ?? ($post['locale'] ?? null)), '/');
    }
}

if (! function_exists('post_url')) {
    /** Absolute-URL counterpart to post_path() — see its docblock. */
    function post_url(array $post, ?string $locale = null): string
    {
        return base_url(post_path($post, $locale));
    }
}

if (! function_exists('theme_language_switcher')) {
    /**
     * Entries for a theme's language switcher — [] unless multi-language
     * is on and more than one language is active, so themes can render it
     * unconditionally. Each entry links to this page's translation when the
     * controller registered one, otherwise to that language's home page.
     *
     * @return list<array{code: string, name: string, native_name: string, url: string, is_current: bool, has_translation: bool}>
     */
    function theme_language_switcher(): array
    {
        $locale = Services::locale();
        $active = $locale->active();

        if (! $locale->enabled() || count($active) < 2) {
            return [];
        }

        $alternates = $locale->alternates();
        $current    = $locale->current();

        return array_map(static fn (array $lang) => [
            'code'            => $lang['code'],
            'name'            => $lang['name'],
            'native_name'     => $lang['native_name'] ?: $lang['name'],
            'url'             => $alternates[$lang['code']] ?? $locale->homeUrl($lang['code']),
            'is_current'      => $lang['code'] === $current,
            'has_translation' => isset($alternates[$lang['code']]),
        ], $active);
    }
}

if (! function_exists('theme_breadcrumbs')) {
    /**
     * Renders a Home -> ... -> Current-page trail (as built by a theme's
     * own trail function, e.g. Super Travel's st_breadcrumb_trail()) as a
     * <nav aria-label="Breadcrumb"><ol>...</ol></nav>. The last crumb is
     * plain text (the current page, not a link to itself) — mirrors the
     * BreadcrumbList JSON-LD a theme emits alongside it via
     * seo_breadcrumb_schema(), so what a visitor sees and what Google
     * reads describe the same trail. Generic/theme-agnostic on purpose:
     * only the CSS class name is theme-flavoured ("lcms-breadcrumbs") —
     * a theme wanting its own look just styles that class rather than
     * needing its own renderer.
     *
     * @param array<int, array{name: string, url: string}> $trail
     */
    function theme_breadcrumbs(array $trail): string
    {
        if (count($trail) < 2) {
            return '';
        }

        $last  = array_key_last($trail);
        $items = [];

        foreach ($trail as $i => $crumb) {
            $items[] = $i === $last
                ? '<li aria-current="page">' . esc($crumb['name']) . '</li>'
                : '<li><a href="' . esc($crumb['url'], 'attr') . '">' . esc($crumb['name']) . '</a></li>';
        }

        return '<nav class="lcms-breadcrumbs" aria-label="Breadcrumb"><ol>' . implode('', $items) . '</ol></nav>';
    }
}

if (! function_exists('theme_render_menu_items')) {
    /**
     * Renders a theme_menu() tree as nested <ul><li><a> markup at any
     * depth — Admin -> Menus lets an admin nest items as deep as they like
     * to build a silo, so the theme can't stop at one hardcoded sub-level.
     * The top-level <ul> is left bare (themes already style ".foo ul" for
     * it); every nested <ul> gets $subUlClass (e.g. "lcms-nav__submenu"),
     * at every depth, so the theme's CSS only needs one flyout rule that
     * also matches itself one level deeper.
     */
    function theme_render_menu_items(array $items, string $subUlClass): string
    {
        if ($items === []) {
            return '<ul></ul>';
        }

        $html = '<ul>';

        foreach ($items as $item) {
            $html .= theme_render_menu_item($item, $subUlClass);
        }

        return $html . '</ul>';
    }
}

if (! function_exists('theme_render_menu_item')) {
    function theme_render_menu_item(array $item, string $subUlClass): string
    {
        $html = '<li><a href="' . esc($item['url'] ?? '#', 'attr') . '"';

        if (! empty($item['target'])) {
            $html .= ' target="' . esc($item['target'], 'attr') . '"';
        }

        if (! empty($item['css_class'])) {
            $html .= ' class="' . esc($item['css_class'], 'attr') . '"';
        }

        $html .= '>' . esc($item['title'] ?? '') . '</a>';

        if (! empty($item['children'])) {
            $html .= '<ul class="' . esc($subUlClass, 'attr') . '">';

            foreach ($item['children'] as $child) {
                $html .= theme_render_menu_item($child, $subUlClass);
            }

            $html .= '</ul>';
        }

        return $html . '</li>';
    }
}

if (! function_exists('theme_menu')) {
    /**
     * Nested menu items for a registered menu location (PRD §3.3.A.3).
     */
    function theme_menu(string $location): array
    {
        $menu = (new \App\Models\MenuModel())->findByLocation($location);

        if (! $menu) {
            return [];
        }

        return (new \App\Models\MenuItemModel())->treeForMenu((int) $menu['id']);
    }
}
