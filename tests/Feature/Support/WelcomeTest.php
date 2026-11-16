<?php

use App\Support\Welcome;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;

it('remembers a web browser was welcomed in a long-lived cookie', function () {
    expect(Welcome::seen())->toBeFalse();

    Welcome::markSeen();

    $cookie = Cookie::queued(Welcome::COOKIE);

    expect(Welcome::seen())->toBeTrue()
        ->and($cookie->getExpiresTime())->toBeGreaterThan(time() + 365 * 24 * 60 * 60);
});

it('knows a browser was welcomed from its cookie', function () {
    $this->withCookie(Welcome::COOKIE, '1')->get('/up');

    expect(Welcome::seen())->toBeTrue();
});

it('remembers on the phone in its device settings, not a cookie', function () {
    useMobileBuild();

    expect(Welcome::seen())->toBeFalse();

    Welcome::markSeen();
    Welcome::markSeen();

    expect(Welcome::seen())->toBeTrue()
        ->and(DB::table('device_settings')->where('key', Welcome::SETTING)->count())->toBe(1)
        ->and(Cookie::hasQueued(Welcome::COOKIE))->toBeFalse();
});
