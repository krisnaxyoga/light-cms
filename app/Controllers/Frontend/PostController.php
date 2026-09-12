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

class PostController extends BaseController
{
    use HandlesWordPress;

    public function show(string $slug)
    {
        $postModel = new PostModel();
        $post      = $postModel->findBySlug($slug);

        if (! $post || $post['status'] !== 'published') {
            return $this->notFoundOrRedirect($slug);
        }

        if (lcms_wp_active()) {
            $postModel->incrementViewCount((int) $post['id']);

            return $this->wpRespond(fn () => lcms_wp_renderer()->singular($post));
        }

        $post = $postModel->withRelations($post);
        $post['content_html'] = (new BlockRenderer())->render($post['content'] ?? '[]');

        $author = $post['author_id'] ? (new UserModel())->find($post['author_id']) : null;
        $post['author_name'] = $author['display_name'] ?? $author['username'] ?? null;

        $postModel->incrementViewCount((int) $post['id']);

        $comments = (new CommentModel())->approvedForPost((int) $post['id']);

        $seoHtml = seo_meta_tags($post, $post['seo_meta'] ?? null);

        $template = $post['post_type'] === 'page' ? 'page' : 'single';

        return $this->response->setBody(theme_engine()->render($template, [
            'seoHtml'    => $seoHtml,
            'post'       => $post,
            'comments'   => $comments,
            'categories' => (new CategoryModel())->findAll(10),
        ]));
    }

    public function category(string $slug)
    {
        $categoryModel = new CategoryModel();
        $category      = $categoryModel->findBySlug($slug);

        if (! $category) {
            return $this->notFoundOrRedirect('category/' . $slug);
        }

        if (lcms_wp_active()) {
            $term = TermMapper::fromCategory($category);

            return $this->wpRespond(fn () => lcms_wp_renderer()->term($term, $this->wpPaged()));
        }

        $postModel = new PostModel();
        $perPage   = (int) (new SettingModel())->get('posts_per_page', 10);

        $posts = \Config\Database::connect()->table('posts p')
            ->select('p.id, p.title, p.slug, p.excerpt, p.published_at')
            ->join('post_categories pc', 'pc.post_id = p.id')
            ->where('pc.category_id', $category['id'])
            ->where('p.status', 'published')
            ->orderBy('p.published_at', 'DESC')
            ->get($perPage)->getResultArray();

        $seoHtml = seo_meta_tags([
            'title'   => $category['name'],
            'slug'    => 'category/' . $category['slug'],
            'excerpt' => $category['meta_description'] ?? $category['description'] ?? '',
        ]);

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
