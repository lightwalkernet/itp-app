<?php

namespace App\Auth;

use Native\Mobile\SecureStorage;

/**
 * Which user this phone is signed in as, kept in the keychain next to the token
 * (platform.md §5). It outlives the session, so the phone stays signed in until the user
 * signs out.
 */
class DeviceAccount
{
    protected const KEY = 'itp.user_id';

    public function __construct(protected SecureStorage $storage) {}

    public function userId(): ?string
    {
        return $this->storage->get(self::KEY);
    }

    public function remember(string $userId): void
    {
        $this->storage->set(self::KEY, $userId);
    }

    public function forget(): void
    {
        $this->storage->delete(self::KEY);
    }
}
