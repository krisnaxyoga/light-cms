<?php

use Config\Services;

if (! function_exists('seo_meta_tags')) {
    /**
     * Render the full <head> SEO block for a post/page (PRD §3.1.A.1).
     * Usage in a theme template: <?= seo_meta_tags($post, $post['seo_meta'] ?? null) ?>
     */
    function seo_meta_tags(array $post, ?array $seoMeta = null): string
    {
        $tags = Services::seoMetaBuilder()->build($post, $seoMeta);

        return Services::seoMetaBuilder()->render($tags);
    }
}

if (! function_exists('seo_schema_tag')) {
    function seo_schema_tag(array $schema): string
    {
        return Services::seoSchemaGenerator()->toScriptTag($schema);
    }
}

if (! function_exists('seo_article_schema')) {
    function seo_article_schema(array $post, array $author = [], ?string $image = null): string
    {
        $schema = Services::seoSchemaGenerator()->article($post, $author, $image);

        return seo_schema_tag($schema);
    }
}

if (! function_exists('seo_score_badge')) {
    /**
     * Small traffic-light badge for admin post lists (PRD §3.1.A.3).
     */
    function seo_score_badge(int $score): string
    {
        // The analyzer's green/yellow/red rating, mapped to daisyUI badges.
        $class = match (Services::seoAnalyzer()->rating($score)) {
            'green'  => 'badge-success',
            'yellow' => 'badge-warning',
            default  => 'badge-error',
        };

        return '<span class="badge badge-sm ' . $class . '">' . $score . '</span>';
    }
}
