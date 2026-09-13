<?php

namespace App\Libraries\I18n;

use App\Models\LanguageModel;
use Config\Services;
use Throwable;

/**
 * Per-request language context for the frontend.
 *
 * URL scheme: the default language lives at the site root exactly as a
 * single-language site would (/about-us); every other active language is
 * prefixed with its url_prefix (/id/tentang-kami). Nothing here is active
 * until the admin flips the "multilang_enabled" setting — with it off the
 * site behaves as if this class did not exist.
 *
 * LocaleFilter sets current() from the URL before any controller runs;
 * controllers register the translated URLs of the page they render via
 * setAlternates() so the theme's language switcher and the hreflang tags
 * can point at the actual translation rather than the other language's home.
 */
class LocaleManager
{
    protected ?array $languages = null;
    protected ?string $current  = null;

    /** @var array<string, string> locale code => absolute URL of this page in that language */
    protected array $alternates = [];

    public function enabled(): bool
    {
        return (string) site_setting('multilang_enabled', '0') === '1';
    }

    /**
     * Every configured language (active or not), default first.
     * Survives the languages table not existing yet (pre-migration) by
     * pretending only the built-in default exists.
     */
    public function all(): array
    {
        if ($this->languages !== null) {
            return $this->languages;
        }

        $cache  = Services::cache();
        $cached = $cache->get(LanguageModel::CACHE_KEY);

        if (is_array($cached)) {
            return $this->languages = $cached;
        }

        try {
            $rows = (new LanguageModel())->allOrdered();
        } catch (Throwable) {
            $rows = [];
        }

        if ($rows === []) {
            $rows = [$this->builtinDefault()];
        }

        $cache->save(LanguageModel::CACHE_KEY, $rows, config(\Config\LightCMS::class)->queryCacheTTL);

        return $this->languages = $rows;
    }

    /**
     * Languages the frontend actually serves: only the default when the
     * feature is off, otherwise every active one (default first).
     */
    public function active(): array
    {
        if (! $this->enabled()) {
            return [$this->default()];
        }

        return array_values(array_filter($this->all(), static fn (array $lang) => (int) $lang['is_active'] === 1));
    }

    public function default(): array
    {
        foreach ($this->all() as $lang) {
            if ((int) $lang['is_default'] === 1) {
                return $lang;
            }
        }

        return $this->all()[0] ?? $this->builtinDefault();
    }

    public function defaultCode(): string
    {
        return $this->default()['code'];
    }

    public function find(string $code): ?array
    {
        foreach ($this->all() as $lang) {
            if ($lang['code'] === $code) {
                return $lang;
            }
        }

        return null;
    }

    /**
     * The active, non-default language owning a URL prefix — null for the
     * default language (it has no prefix) and for anything unknown, so a
     * post slug that happens to look like a code is never misread.
     */
    public function byPrefix(string $segment): ?array
    {
        if (! $this->enabled() || $segment === '') {
            return null;
        }

        $segment = strtolower($segment);

        foreach ($this->active() as $lang) {
            if ((int) $lang['is_default'] !== 1 && strtolower($lang['url_prefix']) === $segment) {
                return $lang;
            }
        }

        return null;
    }

    public function current(): string
    {
        return $this->current ?? $this->defaultCode();
    }

    public function setCurrent(string $code): void
    {
        $this->current = $code;
    }

    public function isDefault(?string $code = null): bool
    {
        return ($code ?? $this->current()) === $this->defaultCode();
    }

    /**
     * Site-relative path for $path in the given language: "/slug" for the
     * default language, "/id/slug" for a prefixed one.
     */
    public function path(string $path = '', ?string $code = null): string
    {
        $path = ltrim($path, '/');
        $lang = $code === null ? null : $this->find($code);

        if (! $this->enabled() || $lang === null || (int) $lang['is_default'] === 1) {
            return '/' . $path;
        }

        return '/' . $lang['url_prefix'] . ($path === '' ? '' : '/' . $path);
    }

    /**
     * Absolute URL for $path in the given language (defaults to the current
     * request language). Drop-in for base_url() in anything that links to
     * content.
     */
    public function url(string $path = '', ?string $code = null): string
    {
        return base_url(ltrim($this->path($path, $code ?? $this->current()), '/'));
    }

    public function homeUrl(?string $code = null): string
    {
        return $this->url('', $code);
    }

    /** @param array<string, string> $map locale code => absolute URL */
    public function setAlternates(array $map): void
    {
        $this->alternates = $map;
    }

    /** @return array<string, string> */
    public function alternates(): array
    {
        return $this->alternates;
    }

    public function flushCache(): void
    {
        Services::cache()->delete(LanguageModel::CACHE_KEY);
        $this->languages = null;
    }

    protected function builtinDefault(): array
    {
        $code = config(\Config\App::class)->defaultLocale ?: 'en';

        return [
            'id'          => 0,
            'code'        => $code,
            'url_prefix'  => $code,
            'name'        => 'English',
            'native_name' => 'English',
            'is_default'  => 1,
            'is_active'   => 1,
            'sort_order'  => 0,
        ];
    }
}
