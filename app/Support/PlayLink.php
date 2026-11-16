<?php

namespace App\Support;

use InvalidArgumentException;
use Itp\Core\Models\Itp;
use Itp\Core\Models\UniversalItp;

/**
 * The path that opens the player (player contract): one piece of one ITP, and where the person
 * came from, so finishing takes them back there. A universal ITP by its slug, a personal one by
 * its id. Paths, not absolute URLs (the phone serves pages from php://127.0.0.1).
 */
final class PlayLink
{
    /**
     * @var list<string>
     */
    public const array PIECES = ['itp', 'breathe', 'why'];

    /**
     * @var list<string>
     */
    public const array FROM = ['today', 'welcome'];

    /**
     * @param  'itp'|'breathe'|'why'  $piece
     * @param  'today'|'welcome'|null  $from
     */
    public static function for(UniversalItp|Itp $itp, string $piece, ?string $from = null): string
    {
        if (! in_array($piece, self::PIECES, true)) {
            throw new InvalidArgumentException("There is no [{$piece}] piece to play.");
        }

        if ($from !== null && ! in_array($from, self::FROM, true)) {
            throw new InvalidArgumentException("The player can't return to [{$from}].");
        }

        return route('play', [
            'source' => $itp instanceof UniversalItp ? 'universal' : 'itp',
            'key' => $itp instanceof UniversalItp ? $itp->slug : $itp->getKey(),
            'piece' => $piece,
            ...($from === null ? [] : ['from' => $from]),
        ], absolute: false);
    }
}
