<?php

namespace App\Libraries\WordPress\Exceptions;

use RuntimeException;

/**
 * wp_redirect() raised as an exception so the calling controller can
 * return a real CodeIgniter RedirectResponse instead of the header()+exit
 * pair WordPress uses (which would bypass the response pipeline and any
 * after-filters).
 */
class WordPressRedirectException extends RuntimeException
{
    public function __construct(protected string $location, protected int $status = 302)
    {
        parent::__construct('Redirect to ' . $location, $status);
    }

    public function location(): string
    {
        return $this->location;
    }

    public function statusCode(): int
    {
        return $this->status;
    }
}
