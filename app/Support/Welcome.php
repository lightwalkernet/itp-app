<?php

namespace App\Support;

use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Itp\Core\Models\UniversalItp;

/**
 * The first launch (player contract): "It's totally possible" and Enter the Field, which plays
 * the Introduction. Shown once per device, to guests only, and never again once the Introduction
 * was played or skipped. The phone remembers in its device-only `device_settings` table; a web
 * browser in a long-lived cookie.
 */
final class Welcome
{
    public const string INTRODUCTION_SLUG = 'introduction';

    public const string COOKIE = 'itp_welcomed';

    /**
     * The `device_settings` row on the phone.
     */
    public const string SETTING = 'welcomed';

    public static function seen(): bool
    {
        if (Runtime::isMobile()) {
            return DB::table('device_settings')->where('key', self::SETTING)->exists();
        }

        return request()->cookie(self::COOKIE) !== null || Cookie::hasQueued(self::COOKIE);
    }

    public static function markSeen(): void
    {
        if (Runtime::isMobile()) {
            DB::table('device_settings')->upsert(
                [['key' => self::SETTING, 'value' => now()->toIso8601String(), 'created_at' => now(), 'updated_at' => now()]],
                ['key'],
                ['value', 'updated_at'],
            );

            return;
        }

        Cookie::queue(Cookie::forever(self::COOKIE, '1'));
    }

    /**
     * The Introduction the welcome plays, or null while it isn't in the content: then there is
     * nothing to welcome with, and the welcome is skipped.
     */
    public static function introduction(): ?UniversalItp
    {
        return UniversalItp::query()->where('slug', self::INTRODUCTION_SLUG)->first();
    }
}
