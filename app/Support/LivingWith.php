<?php

namespace App\Support;

use Itp\Core\Enums\LivingWithType;
use Itp\Core\Models\Itp;
use Itp\Core\Models\UniversalItp;
use Itp\Core\Models\User;

/**
 * The one ITP a person lives with (docs/design/what-we-ask.md). Until they choose, and for guests,
 * it is Meet Your ITP Self. Null means that piece isn't in the content yet: show it as coming soon,
 * never another ITP in its place.
 */
final class LivingWith
{
    public const string DEFAULT_SLUG = 'meet-your-itp-self';

    public static function for(?User $user): UniversalItp|Itp|null
    {
        return $user?->settings?->livingWith() ?? self::default();
    }

    public static function default(): ?UniversalItp
    {
        return UniversalItp::query()->where('slug', self::DEFAULT_SLUG)->first();
    }

    public static function is(?User $user, UniversalItp|Itp $itp): bool
    {
        $current = self::for($user);

        return $current !== null && $current::class === $itp::class && $current->getKey() === $itp->getKey();
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
