<?php

namespace App\Auth;

/**
 * Where the Sanctum token from itp-server is kept: the only difference between the builds in
 * how sign-in works (platform.md §5). The web build keeps it in the session; the mobile build
 * keeps it in SecureStorage.
 */
interface TokenStore
{
    public function get(): ?string;

    public function put(string $token): void;

    public function forget(): void;
}
