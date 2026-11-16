<?php

use App\Support\GuestChoice;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Itp\Core\Models\UniversalItp;

/*
 * A guest's choice of ITP, kept on the device (player contract item 5): a cookie on the web, the
 * device-only settings table on the phone. Carrying it into settings at sign-in is covered in
 * SignInTest and MobileSignInTest.
 */

it('keeps a guest\'s choice in a long-lived cookie on the web', function () {
    $this->travelTo('2026-11-17 09:00:00');
    $itp = UniversalItp::factory()->preview()->create(['slug' => 'awaken-wonder']);

    GuestChoice::choose($itp);

    $cookie = Cookie::queued(GuestChoice::COOKIE);
    expect($cookie->getValue())->toBe('awaken-wonder')
        ->and($cookie->getExpiresTime())->toBeGreaterThanOrEqual(strtotime('2027-12-22'));
    expect(GuestChoice::get()?->is($itp))->toBeTrue();
    $this->assertDatabaseCount('device_settings', 0);
});

it('reads the choice from the cookie the browser sends', function () {
    $itp = UniversalItp::factory()->preview()->create(['slug' => 'healing']);
    request()->cookies->set(GuestChoice::COOKIE, 'healing');

    expect(GuestChoice::get()?->is($itp))->toBeTrue();
});

it('keeps a guest\'s choice in the device settings on the phone, and forgets it there', function () {
    useMobileBuild();
    $itp = UniversalItp::factory()->preview()->create(['slug' => 'abundance']);

    GuestChoice::choose($itp);

    expect(DB::table('device_settings')->where('key', 'guest_itp')->value('value'))->toBe('abundance')
        ->and(Cookie::queued(GuestChoice::COOKIE))->toBeNull()
        ->and(GuestChoice::get()?->is($itp))->toBeTrue();

    GuestChoice::forget();

    expect(GuestChoice::get())->toBeNull();
    $this->assertDatabaseMissing('device_settings', ['key' => 'guest_itp']);
});

it('lets a guest choose only an open Universal', function (string $kind, bool $isPreview) {
    $itp = UniversalItp::factory()->create(['kind' => $kind, 'is_preview' => $isPreview]);

    GuestChoice::choose($itp);
})->with([
    'a locked Universal' => ['universal', false],
    'the Introduction' => ['base', true],
    'a primer' => ['primer', true],
])->throws(InvalidArgumentException::class);

it('ignores a stored choice that is no longer open to guests, or no longer there', function (string $slug) {
    UniversalItp::factory()->create(['slug' => 'purpose', 'is_preview' => false]);
    request()->cookies->set(GuestChoice::COOKIE, $slug);

    expect(GuestChoice::get())->toBeNull();
})->with(['no longer open' => 'purpose', 'removed' => 'no-such-itp']);
