<?php

namespace App\Auth;

use Illuminate\Support\Facades\Cookie;

/**
 * Keeps the web build signed in on a browser the user said is theirs (platform.md §5). The
 * encrypted cookie is the browser's version of the phone's keychain: the user's id and API
 * token, for `itp.trusted_device_days` days from sign-in. It isn't renewed on later visits.
 */
class TrustedBrowser
{
    public const COOKIE = 'itp_trusted_browser';

    public function remember(string $userId, string $token): void
    {
        $days = (int) config('itp.trusted_device_days');

        Cookie::queue(Cookie::make(self::COOKIE, json_encode([
            'user_id' => $userId,
            'token' => $token,
            'expires_at' => now()->addDays($days)->getTimestamp(),
        ], JSON_THROW_ON_ERROR), $days * 24 * 60));
    }

    /**
     * The cookie's contents. Its own expiry is checked too, because a browser's is only a request.
     *
     * @return array{user_id: string, token: string}|null
     */
    public function read(): ?array
    {
        $cookie = request()->cookie(self::COOKIE);
        $data = is_string($cookie) ? json_decode($cookie, true) : null;

        if (! is_string($data['user_id'] ?? null) || ! is_string($data['token'] ?? null) || ! is_int($data['expires_at'] ?? null)) {
            return null;
        }

        if ($data['expires_at'] <= now()->getTimestamp()) {
            return null;
        }

        return ['user_id' => $data['user_id'], 'token' => $data['token']];
    }

    public function forget(): void
    {
        Cookie::queue(Cookie::forget(self::COOKIE));
    }
}
