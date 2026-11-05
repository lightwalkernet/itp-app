<?php

namespace App\Api\Exceptions;

use RuntimeException;

/**
 * itp-server answered 426: this app version is below its minimum. The app shows the update
 * screen; local data is untouched (platform.md §4).
 */
class AppUpdateRequired extends RuntimeException
{
    public function __construct(public readonly ?string $minimumVersion)
    {
        parent::__construct('This version of the app needs an update.');
    }
}
