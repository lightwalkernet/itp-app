<?php

namespace App\Api;

/**
 * What itp-server returns for a spent sign-in code.
 */
final readonly class SignIn
{
    /**
     * @param  array<string, mixed>  $user  the user row as the API returns it
     */
    public function __construct(
        public array $user,
        public string $token,
        public bool $needsProfile,
    ) {}
}
