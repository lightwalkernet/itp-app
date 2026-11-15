<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Itp\Core\Enums\EntryKind;
use Itp\Core\Enums\LivingWithType;
use Itp\Core\Models\Entry;
use Itp\Core\Models\Itp;
use Itp\Core\Models\UniversalItp;
use Itp\Core\Models\User;

/**
 * What a person kept on one app day (brief 1 §2): one entry per user, day and kind. Nothing
 * stops two devices writing the same kind offline, so reads take the newest and a keep replaces
 * the newest rather than adding another.
 */
final class DayEntries
{
    /**
     * The evening entry kept for "Not tonight — that's allowed".
     */
    public const string NOT_TONIGHT = '—';

    /**
     * The newest entry of each kind for the day, keyed by kind.
     *
     * @return Collection<string, Entry>
     */
    public static function for(User $user, string $date): Collection
    {
        return $user->entries()
            ->where('for_date', $date)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->unique(fn (Entry $entry): string => $entry->kind->value)
            ->keyBy(fn (Entry $entry): string => $entry->kind->value);
    }

    /**
     * Keep the words for the day and kind: replace the newest entry if there is one, else add it.
     * An evening entry carries the day's Quantum Why it was asked against. Written through the
     * model, so the row syncs (platform.md §4).
     */
    public static function keep(User $user, string $date, EntryKind $kind, string $body, UniversalItp|Itp|null $livingWith): Entry
    {
        $entry = self::for($user, $date)->get($kind->value);

        $attributes = [
            'body' => $body,
            'living_with_type' => $livingWith === null ? null : LivingWithType::of($livingWith),
            'living_with_id' => $livingWith?->getKey(),
        ];

        if ($kind === EntryKind::Evening) {
            $attributes['why'] = AppDay::why($livingWith?->quantum_whys, $date);
        }

        if ($entry !== null) {
            $entry->update($attributes);

            return $entry;
        }

        return $user->entries()->create([
            'kind' => $kind,
            'for_date' => $date,
            ...$attributes,
        ]);
    }
}
