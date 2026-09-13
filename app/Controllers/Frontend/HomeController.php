<?php

namespace App\Controllers\Frontend;

use App\Controllers\BaseController;
use App\Controllers\Frontend\Concerns\HandlesWordPress;
use App\Models\CategoryModel;
use App\Models\PostModel;
use App\Models\SettingModel;
use Config\LightCMS as LightCMSConfig;
use Config\Services;

class HomeController extends BaseController
{
    use HandlesWordPress;

    /**
     * Home page — / for the default language, /{prefix} for the others
     * (PostController::show() forwards those here; LocaleFilter has already
     * set the current language either way).
     */
    public function index()
    {
        // A WordPress theme renders its own home/front-page template; the
        // guest page cache below is skipped because the WP layer has its
        // own hooks that may vary per request.
        if (lcms_wp_active()) {
            return $this->wpRespond(fn () => lcms_wp_renderer()->home($this->wpPaged()));
        }

        $settings = new SettingModel();
        $perPage  = min((int) $settings->get('posts_per_page', 10), config(LightCMSConfig::class)->maxItemsPerPage);
        $page     = (int) ($this->request->getGet('page') ?? 1);
        $code     = Services::locale()->current();

        $cache = Services::cacheManager();
        // Keyed by language too, or /id would serve the cached English page.
        $cacheKey = "page.home.{$code}.{$page}";

        // Full-page cache for guests only (PRD §3.6.A.2) — a logged-in
        // admin should always see fresh data while editing.
        if (! session()->get('isLoggedIn')) {
            $cached = $cache->remember($cacheKey, config(LightCMSConfig::class)->pageCacheTTL, function () use ($perPage) {
                return $this->buildHomeHtml($perPage);
            }, ['posts']);

            return $this->response->setBody($cached);
        }

        return $this->response->setBody($this->buildHomeHtml($perPage));
    }

    protected function buildHomeHtml(int $perPage): string
    {
        $postModel     = new PostModel();
        $categoryModel = new CategoryModel();
        $locale        = Services::locale();
        $code          = $locale->current();
        $localeFilter  = $locale->enabled() ? $code : null;

        $query = $postModel
            ->select('id, title, slug, locale, post_type, excerpt, featured_image, published_at')
            ->where('status', 'published')
            ->where('post_type', 'post')
            ->orderBy('published_at', 'DESC');

        if ($localeFilter !== null) {
            $query->where('locale', $localeFilter);
        }

        $posts = $query->paginate($perPage);

        // Every active language has a home page — cross-link them for
        // hreflang and the switcher.
        $alternates = [];

        if ($locale->enabled()) {
            foreach ($locale->active() as $lang) {
                $alternates[$lang['code']] = $locale->homeUrl($lang['code']);
            }
        }

        $locale->setAlternates($alternates);

        $siteTitle = site_setting('site_title', 'LightCMS');

        $seoHtml = seo_meta_tags([
            'title'   => $siteTitle,
            'slug'    => '',
            'locale'  => $code,
            'excerpt' => site_setting('site_description', ''),
        ], [
            // Homepage title is just the site name — skip the
            // "%title% - %sitename%" template so it doesn't repeat.
            'meta_title' => $siteTitle,
        ], $alternates);

        return theme_engine()->render('home', [
            'seoHtml'     => $seoHtml,
            'posts'       => $posts,
            'pager'       => $postModel->pager,
            'categories'  => $categoryModel->findAll(10),
            'recentPosts' => $postModel->getPublished(5, 0, 'post', $localeFilter),
        ]);
    }
}
