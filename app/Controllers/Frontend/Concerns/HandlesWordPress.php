<?php

namespace App\Controllers\Frontend\Concerns;

use App\Libraries\WordPress\Exceptions\WordPressDieException;
use App\Libraries\WordPress\Exceptions\WordPressJsonException;
use App\Libraries\WordPress\Exceptions\WordPressRedirectException;
use CodeIgniter\HTTP\ResponseInterface;
use Closure;
use Throwable;

/**
 * Shared plumbing for the controllers that can render through the
 * WordPress compatibility layer (the dedicated WP routes, and the native
 * Home/Post controllers when a WordPress theme is active).
 *
 * The compat layer signals redirects, JSON responses and wp_die() with
 * exceptions instead of exit(); this turns each of those into a real
 * CodeIgniter response.
 */
trait HandlesWordPress
{
    protected function wpPaged(): int
    {
        return max(1, (int) ($this->request->getGet('page') ?? $this->request->getGet('paged') ?? 1));
    }

    /**
     * Plugin code reads the superglobals directly; refresh them from the
     * request object so a handler sees the same values CodeIgniter did.
     */
    protected function wpPopulateSuperglobals(): void
    {
        $_GET     = $this->request->getGet() ?? [];
        $_POST    = $this->request->getPost() ?? [];
        $_REQUEST = array_merge($_GET, $_POST);
    }

    protected function wpRespond(Closure $render): ResponseInterface
    {
        try {
            return $this->response->setBody($render());
        } catch (WordPressRedirectException $e) {
            return $this->response->redirect($e->location(), 'auto', $e->statusCode());
        } catch (WordPressJsonException $e) {
            return $this->response->setStatusCode($e->statusCode())->setJSON($e->data());
        } catch (WordPressDieException $e) {
            log_message('error', 'WP compat: wp_die() — ' . $e->getMessage());

            return $this->response
                ->setStatusCode($e->statusCode())
                ->setBody($this->wpErrorPage($e->title() !== '' ? $e->title() : 'Error', $e->getMessage()));
        } catch (Throwable $e) {
            log_message('critical', 'WP compat: render failed — ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

            if (ENVIRONMENT !== 'production') {
                throw $e;
            }

            return $this->response->setStatusCode(500)->setBody(
                $this->wpErrorPage('Something went wrong', 'The theme could not render this page.')
            );
        }
    }

    protected function wpErrorPage(string $title, string $message): string
    {
        return '<!doctype html><html><head><meta charset="utf-8"><title>' . esc($title) . '</title>'
            . '<style>body{font-family:system-ui,sans-serif;margin:4rem auto;max-width:40rem;line-height:1.5;color:#222}'
            . 'code{background:#f4f4f5;padding:.1rem .3rem;border-radius:3px}</style></head><body>'
            . '<h1>' . esc($title) . '</h1><p>' . esc($message) . '</p></body></html>';
    }
}
