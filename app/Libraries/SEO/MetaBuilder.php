<?php

namespace App\Libraries\SEO;

use App\Models\SettingModel;

/**
 * Builds the actual <head> meta tags for a page from its seo_meta row,
 * falling back to global settings/config defaults (PRD §3.1.A.1).
 *
 * Template variables supported in meta_title: %title%, %sitename%, %sep%.
 */
class MetaBuilder
{
    protected SettingModel $settings;

    public function __construct(?SettingModel $settings = null)
    {
        $this->settings = $settings ?? new SettingModel();
    }

    /**
     * @param array      $post     the post/page row (needs at least title, slug)
     * @param array|null $seoMeta  the matching seo_meta row, if any
     */
    public function build(array $post, ?array $seoMeta = null): array
    {
        $defaults   = config(\Config\LightCMS::class)->seoDefaults;
        $siteName   = $this->settings->get('site_title', 'LightCMS');
        $separator  = $this->settings->get('seo_separator', '-');

        $title = $seoMeta['meta_title'] ?? "%title% {$separator} %sitename%";
        $title = $this->applyTemplate($title, $post, $siteName, $separator);

        $description = $seoMeta['meta_description'] ?? ($post['excerpt'] ?? '');
        $description = mb_substr(trim(strip_tags($description)), 0, 320);

        $canonical = $seoMeta['canonical_url'] ?? site_url($post['slug'] ?? '');

        // Auto-fallback chain (PRD ADDENDUM §9.1): manual og_image, then
        // the post's own featured image, then a site-wide default so a
        // share card is never blank.
        $image = ($seoMeta['og_image'] ?? '')
            ?: ($post['featured_image'] ?? '')
            ?: $this->settings->get('default_og_image', '');

        return [
            'title'            => $title,
            'description'      => $description,
            'keywords'         => $seoMeta['meta_keywords'] ?? '',
            'canonical'        => $canonical,
            'robots'           => $this->robotsTag($seoMeta, $defaults),
            'og' => [
                'title'       => $seoMeta['og_title'] ?? $title,
                'description' => $seoMeta['og_description'] ?? $description,
                'image'       => $image,
                'type'        => ($post['post_type'] ?? 'post') === 'post' ? 'article' : 'website',
                'url'         => $canonical,
            ],
            'twitter' => [
                'card'  => $seoMeta['twitter_card'] ?? $defaults['twitter_card'],
                'title' => $seoMeta['og_title'] ?? $title,
                'image' => $image,
            ],
        ];
    }

    protected function applyTemplate(string $template, array $post, string $siteName, string $separator): string
    {
        return strtr($template, [
            '%title%'    => $post['title'] ?? '',
            '%sitename%' => $siteName,
            '%sep%'      => $separator,
        ]);
    }

    protected function robotsTag(?array $seoMeta, array $defaults): string
    {
        $index  = (bool) ($seoMeta['robots_index'] ?? $defaults['robots_index']);
        $follow = (bool) ($seoMeta['robots_follow'] ?? $defaults['robots_follow']);

        return ($index ? 'index' : 'noindex') . ',' . ($follow ? 'follow' : 'nofollow');
    }

    /**
     * Render the full <head> block as a string. Handy for a view to
     * `<?= $metaBuilder->render($tags) ?>` in one line.
     */
    public function render(array $tags): string
    {
        $html = [];
        $html[] = '<title>' . esc($tags['title']) . '</title>';
        $html[] = '<meta name="description" content="' . esc($tags['description'], 'attr') . '">';

        if ($tags['keywords']) {
            $html[] = '<meta name="keywords" content="' . esc($tags['keywords'], 'attr') . '">';
        }

        $html[] = '<link rel="canonical" href="' . esc($tags['canonical'], 'attr') . '">';
        $html[] = '<meta name="robots" content="' . esc($tags['robots'], 'attr') . '">';

        $html[] = '<meta property="og:title" content="' . esc($tags['og']['title'], 'attr') . '">';
        $html[] = '<meta property="og:description" content="' . esc($tags['og']['description'], 'attr') . '">';
        $html[] = '<meta property="og:type" content="' . esc($tags['og']['type'], 'attr') . '">';
        $html[] = '<meta property="og:url" content="' . esc($tags['og']['url'], 'attr') . '">';

        if ($tags['og']['image']) {
            $html[] = '<meta property="og:image" content="' . esc($tags['og']['image'], 'attr') . '">';
        }

        $html[] = '<meta name="twitter:card" content="' . esc($tags['twitter']['card'], 'attr') . '">';
        $html[] = '<meta name="twitter:title" content="' . esc($tags['twitter']['title'], 'attr') . '">';

        if ($tags['twitter']['image']) {
            $html[] = '<meta name="twitter:image" content="' . esc($tags['twitter']['image'], 'attr') . '">';
        }

        return implode("\n", $html);
    }
}
