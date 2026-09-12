<?php

namespace App\Libraries\WordPress\Exceptions;

use RuntimeException;

/**
 * wp_die() as an exception rather than exit(), so a controller can turn
 * it into a proper HTTP response (and a test can assert on it) instead of
 * killing the PHP process mid-request.
 */
class WordPressDieException extends RuntimeException
{
    public function __construct(string $message, protected int $status = 500, protected string $title = '')
    {
        parent::__construct($message, $status);
    }

    public function statusCode(): int
    {
        return $this->status;
    }

    public function title(): string
    {
        return $this->title;
    }
}
