<?php

namespace App\Libraries\Theme;

/**
 * Cache-busting + trivial minification for theme/admin assets
 * (PRD §3.6.D "Asset Cache" and §2.3 "Asset minification & compression").
 * Deliberately dependency-free to keep the "ultra lightweight" budget.
 */
class AssetManager
{
    /**
     * Append a filemtime-based version query string so browsers pick up
     * changes immediately after deploy without needing a hard cache purge.
     */
    public function versionedUrl(string $absolutePath, string $url): string
    {
        if (! is_file($absolutePath)) {
            return $url;
        }

        $version = filemtime($absolutePath);
        $glue    = str_contains($url, '?') ? '&' : '?';

        return "{$url}{$glue}v={$version}";
    }

    public function minifyCss(string $css): string
    {
        $css = preg_replace('#/\*.*?\*/#s', '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        $css = str_replace(['; ', ' {', '{ ', ' }', '} ', ': ', ' ;'], [';', '{', '{', '}', '}', ':', ';'], $css);

        return trim($css);
    }

    public function minifyJs(string $js): string
    {
        // Intentionally conservative: strip only full-line "//" comments
        // and block comments, and collapse blank lines. A real minifier
        // needs an actual JS parser to be safe with strings/regex literals.
        $js = preg_replace('#/\*.*?\*/#s', '', $js);
        $js = preg_replace('/^\s*\/\/.*$/m', '', $js);
        $js = preg_replace('/\n{2,}/', "\n", $js);

        return trim($js);
    }
}
