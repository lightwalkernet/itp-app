<?php

namespace App\Support;

use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Itp\Core\Models\UniversalItp;
use Itp\Core\Models\User;

/**
 * The ITP a guest chose to live with, as in the original app: one of the open (preview) ones,
 * kept on this device only. The phone keeps its slug in `device_settings`; the web in a
 * long-lived encrypted cookie. At sign-in it becomes the user's own choice if they have none yet.
 */
final class GuestChoice
{
    /**
     * The web build's cookie, holding the universal ITP's slug.
     */
    public const string COOKIE = 'itp_guest_itp';

    /**
     * The phone's `device_settings` key, holding the same slug.
     */
    public const string SETTING = 'guest_itp';

    /**
     * The chosen ITP, or null when none is chosen, or the one chosen is no longer open to guests.
     */
    public static function get(): ?UniversalItp
    {
        $slug = Runtime::isMobile() ? self::storedOnThePhone() : self::storedInTheBrowser();

        if (! is_string($slug) || $slug === '') {
            return null;
        }

        $itp = UniversalItp::query()->where('slug', $slug)->first();

        return $itp !== null && self::isOpenTo($itp) ? $itp : null;
    }

    /**
     * Whether a guest may choose this one: an open (preview) ITP that is lived with at all.
     */
    public static function isOpenTo(UniversalItp $itp): bool
    {
        return $itp->is_preview && LivingWith::canBeLivedWith($itp);
    }

    /**
     * @throws InvalidArgumentException when the ITP isn't open to guests
     */
    public static function choose(UniversalItp $itp): void
    {
        if (! self::isOpenTo($itp)) {
            throw new InvalidArgumentException("A guest can't choose [{$itp->slug}]: it isn't open before sign-in.");
        }

        if (Runtime::isMobile()) {
            DB::table('device_settings')->upsert(
                [['key' => self::SETTING, 'value' => $itp->slug, 'created_at' => now(), 'updated_at' => now()]],
                ['key'],
                ['value', 'updated_at'],
            );

            return;
        }

        Cookie::queue(Cookie::forever(self::COOKIE, $itp->slug));
    }

    public static function forget(): void
    {
        if (Runtime::isMobile()) {
            DB::table('device_settings')->where('key', self::SETTING)->delete();

            return;
        }

        Cookie::queue(Cookie::forget(self::COOKIE));
    }

    /**
     * At sign-in: the guest's choice becomes the user's, unless they already live with one.
     * Either way the device's guest choice is cleared.
     */
    public static function carryInto(User $user): void
    {
        $itp = self::get();

        if ($itp === null) {
            return;
        }

        if ($user->settings()->first()?->livingWith() === null) {
            LivingWith::choose($user, $itp);
        }

        self::forget();
    }

    protected static function storedOnThePhone(): ?string
    {
        return DB::table('device_settings')->where('key', self::SETTING)->value('value');
    }

    /**
     * A cookie queued during this request wins over the one the browser sent, so the page
     * re-rendered after choosing already shows the choice.
     */
    protected static function storedInTheBrowser(): ?string
    {
        $queued = Cookie::queued(self::COOKIE);

        if ($queued !== null) {
            return $queued->isCleared() ? null : $queued->getValue();
        }

        $cookie = request()->cookie(self::COOKIE);

        return is_string($cookie) ? $cookie : null;
    }
}
