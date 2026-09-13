<?php

/**
 * Theme bootstrap (PRD §3.3.A.3). Loaded once per request by
 * ThemeEngine — everything here runs before any layout/template output,
 * so only register things; don't echo.
 */

use App\Libraries\Theme\Theme;

Theme::registerMenus([
    'primary' => 'Primary Menu',
    'footer'  => 'Footer Menu',
]);

Theme::registerWidgetArea('sidebar', [
    'name'        => 'Main Sidebar',
    'description' => 'Widgets in this area appear on posts and pages.',
]);

Theme::registerWidgetArea('footer_1', ['name' => 'Footer Column 1']);
Theme::registerWidgetArea('footer_2', ['name' => 'Footer Column 2']);

Theme::addSupport('custom-logo');
Theme::addSupport('post-thumbnails');
Theme::addSupport('custom-background');

Theme::enqueueStyle('lightcms-default', 'assets/css/style.css');
Theme::enqueueScript('lightcms-default', 'assets/js/main.js');

if (! function_exists('theme_get_related_posts')) {
    /**
     * Simple "related posts" helper for single.php — same category,
     * most recent first, excluding the current post.
     */
    function theme_get_related_posts(array $post, int $limit = 4): array
    {
        $categoryIds = array_column($post['categories'] ?? [], 'id');

        if ($categoryIds === []) {
            return [];
        }

        return \Config\Database::connect()->table('posts p')
            ->select('p.id, p.title, p.slug, p.post_type, p.locale, p.featured_image, p.published_at')
            ->distinct()
            ->join('post_categories pc', 'pc.post_id = p.id')
            ->whereIn('pc.category_id', $categoryIds)
            ->where('p.id !=', $post['id'])
            ->where('p.locale', $post['locale'] ?? current_locale())
            ->where('p.post_type', 'post')
            ->where('p.status', 'published')
            ->orderBy('p.published_at', 'DESC')
            ->limit($limit)
            ->get()->getResultArray();
    }
}
