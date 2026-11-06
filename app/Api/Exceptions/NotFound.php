<?php

namespace App\Api\Exceptions;

/**
 * itp-server answered 404: the record doesn't exist, or belongs to someone else. It extends
 * ApiUnavailable, so a caller that only handles "the API couldn't help" still handles this.
 */
class NotFound extends ApiUnavailable
{
    public function __construct()
    {
        parent::__construct();

        $this->message = "We couldn't find that.";
    }
}
