<?php

namespace App\Api\Exceptions;

use RuntimeException;
use Throwable;

/**
 * itp-server couldn't be reached, or answered with an error the app can't act on.
 */
class ApiUnavailable extends RuntimeException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct("We can't reach it's totally possible right now. Try again in a moment.", 0, $previous);
    }
}
