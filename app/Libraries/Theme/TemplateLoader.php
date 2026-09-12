<?php

namespace App\Libraries\Theme;

/**
 * WordPress-style template hierarchy resolver (PRD §3.3.A.1 templates/
 * directory: home, single, page, archive, category, 404). Given a bit of
 * context about the current request, picks the most specific template
 * name that exists in the active theme, falling back sensibly.
 */
class TemplateLoader
{
    public function __construct(protected ThemeEngine $engine)
    {
    }

    public function forHome(): string
    {
        return $this->firstExisting(['home']);
    }

    public function forSingle(string $postType = 'post'): string
    {
        return $this->firstExisting([
            "single-{$postType}",
            $postType === 'page' ? 'page' : 'single',
        ]);
    }

    public function forArchive(): string
    {
        return $this->firstExisting(['archive', 'home']);
    }

    public function forCategory(): string
    {
        return $this->firstExisting(['category', 'archive', 'home']);
    }

    public function forNotFound(): string
    {
        return $this->firstExisting(['404']);
    }

    protected function firstExisting(array $candidates): string
    {
        $themesPath = config(\Config\LightCMS::class)->themesPath;

        foreach ($candidates as $candidate) {
            if (is_file(FCPATH . $themesPath . $this->engine->activeThemeSlug() . '/templates/' . $candidate . '.php')) {
                return $candidate;
            }
        }

        // Nothing matched — let ThemeEngine::render() throw a clear error
        // for the first (most specific) candidate rather than silently
        // rendering the wrong thing.
        return $candidates[0];
    }
}
