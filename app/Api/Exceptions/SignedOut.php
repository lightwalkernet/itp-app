<?php

namespace App\Api\Exceptions;

use RuntimeException;

/**
 * itp-server answered 401: the token was revoked or the account deleted. A 401 on any API call
 * signs the app out (platform.md §5).
 */
class SignedOut extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('You have been signed out.');
    }
}
