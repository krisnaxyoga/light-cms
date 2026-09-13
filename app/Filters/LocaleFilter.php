<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * Reads the language prefix off the front of the URL (/id/...) and
 * records it as the request language — for LocaleManager (content
 * queries, URL building) and for CI4's own request locale, which the
 * themes already echo into <html lang>.
 *
 * Routing itself is untouched: the routes capture the first segment
 * generically and the controllers reject unknown prefixes, so adding a
 * language in the admin needs no code change and no route cache rebuild.
 */
class LocaleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! $request instanceof IncomingRequest) {
            return null;
        }

        $locale = Services::locale();
        $code   = $locale->defaultCode();

        // The request-relative path, normalised whether or not index.php
        // is in the URL (same reasoning as AdminAuthFilter).
        $segment = explode('/', trim($request->getPath(), '/'))[0] ?? '';

        if ($segment !== '' && ($lang = $locale->byPrefix($segment)) !== null) {
            $code = $lang['code'];
        }

        $locale->setCurrent($code);

        // CI4 refuses setLocale() for anything outside App::$supportedLocales,
        // so feed it the admin-managed list first.
        $request->setValidLocales(array_column($locale->active(), 'code') ?: [$code]);
        $request->setLocale($code);

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // no-op
    }
}
