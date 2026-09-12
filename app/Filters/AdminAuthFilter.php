<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Gate for everything under /admin except the login screen itself
 * (PRD §3.5 "role-based permissions"). Role-level checks belong in each
 * controller/action — this filter only proves "someone is logged in".
 */
class AdminAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // IncomingRequest::getPath() is the route-relative path CI4 itself
        // matched against — unlike $request->getUri()->getPath(), it is
        // already normalized whether or not index.php/URL rewriting is in
        // play, so this check works the same on `php spark serve` and behind
        // a rewrite-enabled vhost.
        $uri = $request instanceof IncomingRequest ? trim($request->getPath(), '/') : '';

        // The login screen (and its own POST handler) must stay reachable
        // by definition — everything else under admin/* is gated.
        if ($uri === 'admin/login' || session()->get('isLoggedIn')) {
            return null;
        }

        session()->setFlashdata('error', 'Please log in to continue.');

        return redirect()->to('/admin/login');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // no-op
    }
}
