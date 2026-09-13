<?php

use Config\Services;

if (! function_exists('seo_meta_tags')) {
    /**
     * Render the full <head> SEO block for a post/page (PRD §3.1.A.1).
     * Usage in a theme template: <?= seo_meta_tags($post, $post['seo_meta'] ?? null) ?>
     *
     * @param array<string, string> $alternates locale code => absolute URL of
     *                                          this page's translations (emitted as hreflang links)
     */
    function seo_meta_tags(array $post, ?array $seoMeta = null, array $alternates = []): string
    {
        $tags = Services::seoMetaBuilder()->build($post, $seoMeta, $alternates);

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

if (! function_exists('seo_breadcrumb_schema')) {
    /**
     * BreadcrumbList JSON-LD for a trail built by a theme (e.g.
     * st_breadcrumb_trail() in Super Travel's functions.php).
     *
     * @param array<int, array{name: string, url: string}> $trail Home first, current page last.
     */
    function seo_breadcrumb_schema(array $trail): string
    {
        if (count($trail) < 2) {
            // A single crumb (just "Home") isn't a meaningful trail —
            // Google's own guidance is to only mark up real hierarchies.
            return '';
        }

        $schema = Services::seoSchemaGenerator()->breadcrumb($trail);

        return seo_schema_tag($schema);
    }
}

if (! function_exists('seo_schema_data_tag')) {
    /**
     * Wraps the already-encoded JSON sitting in seo_meta.schema_data (built
     * by AutoSeoGenerator::buildSchema() and saved by
     * PostController::saveSeoMeta() on every post/page save — an Article,
     * plus an FAQPage merged in via @graph when the body has 2+ detected
     * Q/A pairs) in a <script> tag.
     *
     * Takes the raw string directly rather than json_decode()-ing it and
     * going through seo_schema_tag() (array -> re-encode): $post['seo_meta']
     * is fetched with a plain query in PostModel::withRelations(), not
     * through SEOModel, so SEOModel's json-array cast on this column never
     * runs and it arrives as the JSON string it's stored as.
     */
    function seo_schema_data_tag(?string $schemaDataJson): string
    {
        return $schemaDataJson ? '<script type="application/ld+json">' . $schemaDataJson . '</script>' : '';
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
