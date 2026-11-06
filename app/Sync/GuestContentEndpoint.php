<?php

namespace App\Sync;

/**
 * The same API host and headers as the signed-in sync, with no token: itp-server's public
 * content pull needs none (content bundle contract).
 */
class GuestContentEndpoint extends ApiServerEndpoint
{
    public function token(): ?string
    {
        return null;
    }
}
