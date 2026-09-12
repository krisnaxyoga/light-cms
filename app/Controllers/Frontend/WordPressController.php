<?php

namespace App\Controllers\Frontend;

use App\Controllers\BaseController;
use App\Controllers\Frontend\Concerns\HandlesWordPress;
use App\Libraries\WordPress\Bridge\TermMapper;
use App\Libraries\WordPress\Bridge\UserMapper;
use App\Libraries\WordPress\Hooks;
use App\Models\PostModel;
use App\Models\UserModel;

/**
 * Frontend routes served by the WordPress compatibility layer: the
 * archives and endpoints that have no LightCMS equivalent (tag, author,
 * date, search, admin-ajax, admin-post).
 *
 * The home page and single posts/categories stay on the native LightCMS
 * controllers, which delegate here when a WordPress theme is active — so
 * caching, redirects and 404 logging keep working either way.
 */
class WordPressController extends BaseController
{
    use HandlesWordPress;

    public function index()
    {
        if (! lcms_wp_active()) {
            return $this->notFound();
        }

        return $this->wpRespond(fn () => lcms_wp_renderer()->home($this->wpPaged()));
    }

    public function single(string $slug)
    {
        if (! lcms_wp_active()) {
            return $this->notFound();
        }

        $post = (new PostModel())->findBySlug($slug);

        if ($post === null || $post['status'] !== 'published') {
            return $this->notFound();
        }

        return $this->wpRespond(fn () => lcms_wp_renderer()->singular($post));
    }

    public function tag(string $slug)
    {
        if (! lcms_wp_active()) {
            return $this->notFound();
        }

        lcms_wp_boot();
        $term = TermMapper::findBySlug($slug, 'post_tag');

        if ($term === null) {
            return $this->notFound();
        }

        return $this->wpRespond(fn () => lcms_wp_renderer()->term($term, $this->wpPaged()));
    }

    public function author(string $username)
    {
        if (! lcms_wp_active()) {
            return $this->notFound();
        }

        lcms_wp_boot();
        $row = (new UserModel())->where('username', $username)->first();

        if ($row === null) {
            return $this->notFound();
        }

        return $this->wpRespond(fn () => lcms_wp_renderer()->author(UserMapper::fromRow($row), $this->wpPaged()));
    }

    public function search()
    {
        $term = (string) ($this->request->getGet('s') ?? '');

        if (! lcms_wp_active()) {
            return redirect()->to('/');
        }

        return $this->wpRespond(fn () => lcms_wp_renderer()->search($term, $this->wpPaged()));
    }

    public function dateArchive(string $year, ?string $month = null, ?string $day = null)
    {
        if (! lcms_wp_active()) {
            return $this->notFound();
        }

        return $this->wpRespond(fn () => lcms_wp_renderer()->dateArchive(
            (int) $year,
            $month === null ? null : (int) $month,
            $day === null ? null : (int) $day,
            $this->wpPaged()
        ));
    }

    public function postTypeArchive(string $postType)
    {
        if (! lcms_wp_active()) {
            return $this->notFound();
        }

        return $this->wpRespond(fn () => lcms_wp_renderer()->postTypeArchive($postType, $this->wpPaged()));
    }

    /** The theme's 404.php with a real 404 status. */
    public function notFound()
    {
        $this->response->setStatusCode(404);

        if (! lcms_wp_active()) {
            return $this->response->setBody(theme_engine()->render('404', ['suggestions' => []]));
        }

        return $this->wpRespond(fn () => lcms_wp_renderer()->notFound());
    }

    /**
     * wp-admin/admin-ajax.php. Fires wp_ajax_{action} for a logged-in
     * user and wp_ajax_nopriv_{action} for a visitor, like WordPress —
     * whatever the handler echoes becomes the response body.
     */
    public function ajax()
    {
        lcms_wp_boot(lcms_wp_active() ? 'theme' : 'plugins');

        $GLOBALS['lightcms_doing_ajax'] = true;

        $action = (string) ($this->request->getPost('action') ?? $this->request->getGet('action') ?? '');

        if ($action === '') {
            return $this->response->setStatusCode(400)->setBody('0');
        }

        $this->wpPopulateSuperglobals();

        $hook = is_user_logged_in() ? 'wp_ajax_' . $action : 'wp_ajax_nopriv_' . $action;

        if (Hooks::has($hook) === false) {
            return $this->response->setStatusCode(400)->setBody('0');
        }

        return $this->wpRespond(static function () use ($hook) {
            ob_start();
            Hooks::doAction($hook);

            return (string) ob_get_clean();
        });
    }

    /** wp-admin/admin-post.php — the non-AJAX form endpoint. */
    public function adminPost()
    {
        lcms_wp_boot(lcms_wp_active() ? 'theme' : 'plugins');

        $action = (string) ($this->request->getPost('action') ?? $this->request->getGet('action') ?? '');

        if ($action === '') {
            return $this->response->setStatusCode(400)->setBody('Missing action.');
        }

        $this->wpPopulateSuperglobals();

        $hook = is_user_logged_in() ? 'admin_post_' . $action : 'admin_post_nopriv_' . $action;

        return $this->wpRespond(static function () use ($hook) {
            ob_start();
            Hooks::doAction($hook);

            return (string) ob_get_clean();
        });
    }
}
