<?php

namespace App\Api;

/**
 * What itp-server returns for a spent sign-in code.
 */
final readonly class SignIn
{
    /**
     * @param  array<string, mixed>  $user  the user row as the API returns it
     * @param  array<string, mixed>|null  $settings  the user's settings row; null from a server older than brief 1
     */
    public function __construct(
        public array $user,
        public string $token,
        public bool $needsProfile,
        public ?array $settings = null,
    ) {}
}
