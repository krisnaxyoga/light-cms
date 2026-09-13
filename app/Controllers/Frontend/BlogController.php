<?php

namespace App\Controllers\Frontend;

use App\Controllers\BaseController;
use App\Models\CategoryModel;
use App\Models\PostModel;
use App\Models\SettingModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\LightCMS as LightCMSConfig;
use Config\Services;

/**
 * Paginated "all posts" listing at /blog — the native theme's own archive
 * template (templates/archive.php) already existed but had no route
 * pointing at it, since PRD-native browsing normally happens through the
 * homepage (paginated) or a category. Themes that repurpose home.php as a
 * bespoke landing page (see Super Travel) need a real blog listing to
 * link a "Blog" nav item at, so it lives here rather than in HomeController.
 *
 * With multi-language on it is also served at /{prefix}/blog, listing
 * only that language's posts.
 */
class BlogController extends BaseController
{
    public function index(?string $prefix = null)
    {
        $locale = Services::locale();

        if ($prefix !== null && $locale->byPrefix($prefix) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $settings = new SettingModel();
        $perPage  = min((int) $settings->get('posts_per_page', 10), config(LightCMSConfig::class)->maxItemsPerPage);
        $page     = (int) ($this->request->getGet('page') ?? 1);
        $code     = $locale->current();

        $cache    = Services::cacheManager();
        $cacheKey = "page.blog.{$code}.{$page}";

        if (! session()->get('isLoggedIn')) {
            $cached = $cache->remember($cacheKey, config(LightCMSConfig::class)->pageCacheTTL, function () use ($perPage) {
                return $this->buildBlogHtml($perPage);
            }, ['posts']);

            return $this->response->setBody($cached);
        }

        return $this->response->setBody($this->buildBlogHtml($perPage));
    }

    protected function buildBlogHtml(int $perPage): string
    {
        $postModel     = new PostModel();
        $categoryModel = new CategoryModel();
        $locale        = Services::locale();
        $code          = $locale->current();

        $query = $postModel
            ->select('id, title, slug, locale, post_type, excerpt, featured_image, published_at')
            ->where('status', 'published')
            ->where('post_type', 'post')
            ->orderBy('published_at', 'DESC');

        if ($locale->enabled()) {
            $query->where('locale', $code);
        }

        $posts = $query->paginate($perPage);

        $alternates = [];

        if ($locale->enabled()) {
            foreach ($locale->active() as $lang) {
                $alternates[$lang['code']] = $locale->url('blog', $lang['code']);
            }
        }

        $locale->setAlternates($alternates);

        // Admin -> Homepage owns the heading/intro/meta copy for this
        // listing (see App\Libraries\Homepage\HomepageContent); it is not
        // yet locale-aware, so every language currently shares one set of
        // Blog-page copy.
        $blog = homepage_content('blog');

        $seoHtml = seo_meta_tags([
            'title'   => $blog['meta_title'] ?: $blog['heading'],
            'slug'    => 'blog',
            'locale'  => $code,
            'excerpt' => $blog['meta_description'] ?: site_setting('site_description', ''),
        ], null, $alternates);

        return theme_engine()->render('archive', [
            'seoHtml'      => $seoHtml,
            'archiveTitle' => $blog['heading'],
            'archiveIntro' => $blog['intro'],
            'posts'        => $posts,
            'pager'        => $postModel->pager,
            'categories'   => $categoryModel->findAll(10),
        ]);
    }
}
