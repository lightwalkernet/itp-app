<?php

namespace App\Support;

use LogicException;

/**
 * The platform flag (platform.md §3a). Every difference between the web and mobile builds reads
 * this, never NativePHP itself, so behaviour is declared per build and testable.
 */
final class Runtime
{
    public const Server = 'server';

    public const Mobile = 'mobile';

    /**
     * Anything other than an explicit "mobile" is the server (web) build.
     */
    public static function isMobile(): bool
    {
        return config('itp.runtime') === self::Mobile;
    }

    public static function isServer(): bool
    {
        return ! self::isMobile();
    }

    /**
     * The sync package's role follows the flag: the phone is the client.
     */
    public static function syncRole(): string
    {
        return self::isMobile() ? 'client' : 'server';
    }

    /**
     * The web build talks to the shared MySQL database; the phone to its own SQLite file. A
     * mismatch means a build was configured wrongly, so stop before touching any data.
     *
     * @throws LogicException
     */
    public static function assertMatchesDatabase(string $driver): void
    {
        $expected = self::isMobile() ? 'sqlite' : 'mysql';

        if ($driver !== $expected) {
            throw new LogicException(sprintf(
                'ITP_RUNTIME is "%s", so the default database must be %s, not %s.',
                self::isMobile() ? self::Mobile : self::Server,
                $expected,
                $driver,
            ));
        }
    }
}
