<?php

namespace App\Auth;

use Native\Mobile\SecureStorage;

/**
 * The mobile build's token store: the device keychain (iOS) or keystore (Android), so the token
 * never sits in the session or the SQLite file.
 */
class SecureStorageTokenStore implements TokenStore
{
    protected const KEY = 'itp.api_token';

    public function __construct(protected SecureStorage $storage) {}

    public function get(): ?string
    {
        return $this->storage->get(self::KEY);
    }

    public function put(string $token): void
    {
        $this->storage->set(self::KEY, $token);
    }

    public function forget(): void
    {
        $this->storage->delete(self::KEY);
    }
}
