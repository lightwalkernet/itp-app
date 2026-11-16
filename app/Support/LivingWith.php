<?php

namespace App\Support;

use Itp\Core\Enums\LivingWithType;
use Itp\Core\Enums\UniversalItpKind;
use Itp\Core\Models\Itp;
use Itp\Core\Models\UniversalItp;
use Itp\Core\Models\User;

/**
 * The one ITP a person lives with (docs/design/what-we-ask.md), until they change it: the
 * user's choice in their settings, or a guest's on this device (GuestChoice). Null until one
 * is chosen; Today then asks them to choose.
 */
final class LivingWith
{
    /**
     * A base piece that can be lived with, for the first few days.
     */
    public const string MEET_YOUR_ITP_SELF = 'meet-your-itp-self';

    public static function for(?User $user): UniversalItp|Itp|null
    {
        return $user === null ? GuestChoice::get() : $user->settings?->livingWith();
    }

    public static function is(?User $user, UniversalItp|Itp $itp): bool
    {
        $current = self::for($user);

        return $current !== null && $current::class === $itp::class && $current->getKey() === $itp->getKey();
    }

    /**
     * A person's own ITPs, the Universals and Meet Your ITP Self. Not a primer (for a moment,
     * not a day), and not the other base pieces such as the Introduction.
     */
    public static function canBeLivedWith(UniversalItp|Itp $itp): bool
    {
        return $itp instanceof Itp
            || $itp->kind === UniversalItpKind::Universal
            || $itp->slug === self::MEET_YOUR_ITP_SELF;
    }

    /**
     * Live with this one from now on. The settings row comes from sign-in; it is created here only
     * if it somehow isn't there yet.
     */
    public static function choose(User $user, UniversalItp|Itp $itp): void
    {
        $settings = $user->settings()->firstOrCreate();

        $settings->update([
            'living_with_type' => LivingWithType::of($itp),
            'living_with_id' => $itp->getKey(),
        ]);

        $user->setRelation('settings', $settings);
    }
}
