<?php

namespace App\Controllers\Frontend;

use App\Controllers\BaseController;
use App\Controllers\Frontend\Concerns\HandlesWordPress;
use App\Libraries\WordPress\Bridge\TermMapper;
use App\Libraries\Editor\BlockRenderer;
use App\Models\CategoryModel;
use App\Models\CommentModel;
use App\Models\NotFoundLogModel;
use App\Models\NotificationModel;
use App\Models\PostModel;
use App\Models\RedirectModel;
use App\Models\SettingModel;
use App\Models\UserModel;
use Config\LightCMS as LightCMSConfig;
use Config\Services;

class PostController extends BaseController
{
    use HandlesWordPress;

    /**
     * Single post/page. Routed as /slug (default language) and, with
     * multi-language on, /{prefix}/slug — the prefix arrives as $prefix
     * and LocaleFilter has already made it the current language.
     */
    public function show(string $slug, ?string $prefix = null)
    {
        $locale = Services::locale();

        if ($prefix !== null) {
            // Two segments only ever mean "language + slug"; anything else
            // is a URL that never existed.
            if ($locale->byPrefix($prefix) === null) {
                return $this->notFoundOrRedirect($prefix . '/' . $slug);
            }
        } elseif ($locale->byPrefix($slug) !== null) {
            // A bare /id is that language's home page, not a post.
            return $this->forwardToHome();
        }

        $code      = $locale->current();
        $postModel = new PostModel();
        $post      = $postModel->findBySlug($slug, $locale->enabled() ? $code : null);

        if (! $post || $post['status'] !== 'published') {
            return $this->notFoundOrRedirect(trim($this->request->getPath(), '/'));
        }

        if (lcms_wp_active()) {
            // The WP compat layer has its own permalink expectations —
            // the /blog/ canonical scheme below is a native-theme concern
            // only, so a WP theme's URLs are left exactly as it renders them.
            $postModel->incrementViewCount((int) $post['id']);

            return $this->wpRespond(fn () => lcms_wp_renderer()->singular($post));
        }

        // Self-correcting canonical URL: a real blog post lives at
        // /blog/{slug} (post_path()), not the bare /{slug} it used to.
        // Anyone hitting the old shape — a stale bookmark, an already-
        // indexed Google result — 301s to where it lives now instead of
        // silently rendering twice at two different URLs (duplicate
        // content). Pages are unaffected: their canonical path is still
        // just the bare slug, so this is a no-op for them.
        $canonicalPath = post_path($post, $post['locale'] ?? $code);
        $requestPath   = trim($this->request->getPath(), '/');

        if ($requestPath !== $canonicalPath) {
            return $this->response->redirect(base_url($canonicalPath), 'auto', 301);
        }

        $post = $postModel->withRelations($post);
        $post['content_html'] = (new BlockRenderer())->render($post['content'] ?? '[]');

        $author = $post['author_id'] ? (new UserModel())->find($post['author_id']) : null;
        $post['author_name'] = $author['display_name'] ?? $author['username'] ?? null;

        $postModel->incrementViewCount((int) $post['id']);

        $comments = (new CommentModel())->approvedForPost((int) $post['id']);

        $alternates = $this->postAlternates($postModel, $post);
        $locale->setAlternates($alternates);

        $seoHtml = seo_meta_tags($post, $post['seo_meta'] ?? null, $alternates);

        $template = $post['post_type'] === 'page' ? 'page' : 'single';

        return $this->response->setBody(theme_engine()->render($template, [
            'seoHtml'    => $seoHtml,
            'post'       => $post,
            'comments'   => $comments,
            'categories' => (new CategoryModel())->findAll(10),
        ]));
    }

    /**
     * Category archive. Categories are shared across languages, so
     * /category/x and /id/category/x are the same category listing that
     * language's posts.
     */
    public function category(string $slug, ?string $prefix = null)
    {
        $locale = Services::locale();

        if ($prefix !== null && $locale->byPrefix($prefix) === null) {
            return $this->notFoundOrRedirect($prefix . '/category/' . $slug);
        }

        $code          = $locale->current();
        $categoryModel = new CategoryModel();
        $category      = $categoryModel->findBySlug($slug);

        if (! $category) {
            return $this->notFoundOrRedirect($locale->path('category/' . $slug, $code));
        }

        if (lcms_wp_active()) {
            $term = TermMapper::fromCategory($category);

            return $this->wpRespond(fn () => lcms_wp_renderer()->term($term, $this->wpPaged()));
        }

        $perPage = (int) (new SettingModel())->get('posts_per_page', 10);

        $query = \Config\Database::connect()->table('posts p')
            ->select('p.id, p.title, p.slug, p.locale, p.post_type, p.excerpt, p.published_at')
            ->join('post_categories pc', 'pc.post_id = p.id')
            ->where('pc.category_id', $category['id'])
            ->where('p.status', 'published')
            ->orderBy('p.published_at', 'DESC');

        if ($locale->enabled()) {
            $query->where('p.locale', $code);
        }

        $posts = $query->get($perPage)->getResultArray();

        $alternates = $this->sharedPathAlternates('category/' . $category['slug']);
        $locale->setAlternates($alternates);

        $seoHtml = seo_meta_tags([
            'title'   => $category['name'],
            'slug'    => 'category/' . $category['slug'],
            'locale'  => $code,
            'excerpt' => $category['meta_description'] ?? $category['description'] ?? '',
        ], null, $alternates);

        return $this->response->setBody(theme_engine()->render('category', [
            'seoHtml'    => $seoHtml,
            'category'   => $category,
            'posts'      => $posts,
            'categories' => $categoryModel->findAll(10),
        ]));
    }

    /**
     * A slug matched nothing published — check the redirect table
     * (PRD §3.1.B.4) before giving up with a real 404.
     */
    protected function notFoundOrRedirect(string $path)
    {
        $redirect = (new RedirectModel())->match('/' . ltrim($path, '/'));

        if ($redirect) {
            (new RedirectModel())->recordHit((int) $redirect['id']);

            return $this->response->redirect($redirect['target_url'], 'auto', (int) $redirect['redirect_type']);
        }

        $logModel = new NotFoundLogModel();
        $logModel->insert([
            'url'        => mb_substr('/' . ltrim($path, '/'), 0, 255),
            'referer'    => mb_substr((string) $this->request->getServer('HTTP_REFERER'), 0, 255) ?: null,
            'ip_address' => $this->request->getIPAddress(),
        ]);

        $this->maybeNotify404Spike($logModel);

        $this->response->setStatusCode(404);

        // The redirect table and the 404 log above run for both engines;
        // only the rendering differs.
        if (lcms_wp_active()) {
            return $this->wpRespond(fn () => lcms_wp_renderer()->notFound());
        }

        return $this->response->setBody(theme_engine()->render('404', [
            'suggestions' => $logModel->suggestSimilarSlugs($path),
        ]));
    }

    /**
     * Published translations of a post as locale => absolute URL (the post
     * itself included) — feeds hreflang tags and the theme's language
     * switcher. Empty when multi-language is off.
     */
    protected function postAlternates(PostModel $postModel, array $post): array
    {
        $locale = Services::locale();

        if (! $locale->enabled()) {
            return [];
        }

        $active     = array_column($locale->active(), 'code');
        $alternates = [];

        foreach ($postModel->translations($post['translation_group_id'] ?? null) as $code => $sibling) {
            if (in_array($code, $active, true)) {
                $alternates[$code] = post_url($sibling, $code);
            }
        }

        $alternates[$post['locale']] ??= post_url($post, $post['locale']);

        return $alternates;
    }

    /**
     * For pages whose path is the same in every language (home, category
     * archives): one URL per active language.
     */
    protected function sharedPathAlternates(string $path): array
    {
        $locale = Services::locale();

        if (! $locale->enabled()) {
            return [];
        }

        $alternates = [];

        foreach ($locale->active() as $lang) {
            $alternates[$lang['code']] = $locale->url($path, $lang['code']);
        }

        return $alternates;
    }

    /** Render the home page from here (a bare /{prefix} URL). */
    protected function forwardToHome()
    {
        $home = new HomeController();
        $home->initController($this->request, $this->response, $this->logger);

        return $home->index();
    }

    /**
     * PRD ADDENDUM §13 notify404Spike: warn the admin once per window
     * rather than once per hit when unknown URLs start piling up (broken
     * migration, a bad bulk find/replace, a scraper hammering old links).
     */
    protected function maybeNotify404Spike(NotFoundLogModel $logModel): void
    {
        $config = config(LightCMSConfig::class);
        $window = $config->notFoundSpikeWindowMinutes;

        if ($logModel->distinctHitsSince($window) < $config->notFoundSpikeThreshold) {
            return;
        }

        $notifications = new NotificationModel();
        $title         = '404 spike detected';

        if ($notifications->existsRecently($title, $window)) {
            return;
        }

        $notifications->insert([
            'type'        => 'error',
            'title'       => $title,
            'message'     => "More than {$config->notFoundSpikeThreshold} distinct unknown URLs were hit in the last {$window} minutes.",
            'action_url'  => site_url('admin/seo/redirects'),
            'action_text' => 'Review redirects',
        ]);
    }
}
