<?php

namespace App\Support;

/**
 * Small counts in words, as the copy writes them (*Thirteen, for everyone*, *one of eleven*).
 * Plain PHP rather than intl's NumberFormatter, which the phone's PHP may not have.
 */
final class NumberWords
{
    private const array WORDS = [
        'zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten',
        'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen',
        'nineteen', 'twenty',
    ];

    /**
     * The count in words up to twenty, in digits after that.
     */
    public static function spell(int $count): string
    {
        return self::WORDS[$count] ?? (string) $count;
    }

    /**
     * The same, to begin a sentence.
     */
    public static function spellCapitalised(int $count): string
    {
        return ucfirst(self::spell($count));
    }
}
