<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The app's day (docs/design/what-we-ask.md): it runs 05:00 to 05:00 in the person's own time, so
 * a reflection written at half past midnight belongs to the day before. The hour always comes from
 * the device (the web server runs in UTC), so callers pass it in.
 */
final class AppDay
{
    public const int STARTS_AT_HOUR = 5;

    /**
     * morning (5–11), afternoon (12–16), evening (17–20) or night (21–4).
     *
     * @return 'morning'|'afternoon'|'evening'|'night'
     */
    public static function part(int $hour): string
    {
        return match (true) {
            $hour >= 5 && $hour <= 11 => 'morning',
            $hour >= 12 && $hour <= 16 => 'afternoon',
            $hour >= 17 && $hour <= 20 => 'evening',
            default => 'night',
        };
    }

    /**
     * The day a moment belongs to, as Y-m-d: the date five hours earlier.
     */
    public static function date(CarbonInterface $localNow): string
    {
        return CarbonImmutable::instance($localNow)->subHours(self::STARTS_AT_HOUR)->toDateString();
    }

    /**
     * The day's Quantum Why: the same all day, on every device, with no sync (brief 1 §3).
     *
     * @param  list<string>|null  $whys
     */
    public static function why(?array $whys, string $date): ?string
    {
        if ($whys === null || $whys === []) {
            return null;
        }

        $dayOfYear = CarbonImmutable::parse($date)->dayOfYear;

        return array_values($whys)[$dayOfYear % count($whys)];
    }
}
