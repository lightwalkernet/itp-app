<?php

namespace App\Auth;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * The web build's token store. The token is encrypted before it goes into the session, whatever
 * the session driver.
 */
class SessionTokenStore implements TokenStore
{
    protected const KEY = 'itp.api_token';

    public function get(): ?string
    {
        $encrypted = session()->get(self::KEY);

        if (! is_string($encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            return null;
        }
    }

    public function put(string $token): void
    {
        session()->put(self::KEY, Crypt::encryptString($token));
    }

    public function forget(): void
    {
        session()->forget(self::KEY);
    }
}
