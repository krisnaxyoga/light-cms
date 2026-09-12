<?php

namespace App\Libraries\WordPress\Exceptions;

use RuntimeException;

/**
 * wp_send_json() as an exception: the AJAX/REST controller catches it and
 * returns a CodeIgniter JSON response, instead of WordPress's echo+die.
 */
class WordPressJsonException extends RuntimeException
{
    public function __construct(protected mixed $data, protected int $status = 200, protected int $flags = 0)
    {
        parent::__construct('JSON response', $status);
    }

    public function data(): mixed
    {
        return $this->data;
    }

    public function statusCode(): int
    {
        return $this->status;
    }

    public function jsonFlags(): int
    {
        return $this->flags;
    }
}
