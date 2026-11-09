<?php

use App\Auth\TokenStore;
use App\Auth\TrustedBrowser;
use App\Livewire\Home;
use App\Livewire\SignIn;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Itp\Core\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    Http::preventStrayRequests();
    $this->withoutVite();
});

/**
 * The cookie's value, as the browser sends it back.
 */
function trustedBrowserCookie(User|string $user, ?int $expiresAt = null): string
{
    return json_encode([
        'user_id' => $user instanceof User ? $user->id : $user,
        'token' => '1|secret',
        'expires_at' => $expiresAt ?? now()->addDays(30)->getTimestamp(),
    ]);
}

/**
 * Finish signing in with the box ticked or not.
 */
function signInOnWeb(User $user, bool $keepSignedIn): void
{
    Http::fake(['api.itp.test/v1/auth/verify-code' => Http::response(['message' => 'Signed in.', 'errors' => (object) [], 'data' => [
        'user' => ['id' => $user->id, 'email' => $user->email, 'first_name' => $user->first_name],
        'token' => '1|secret',
        'needs_profile' => false,
    ]])]);

    Livewire::test(SignIn::class)
        ->set('email', $user->email)
        ->set('codeSent', true)
        ->set('code', '123456')
        ->set('keepSignedIn', $keepSignedIn)
        ->call('verifyCode')
        ->assertHasNoErrors();
}

it('offers to keep a browser signed in, but not on the phone', function () {
    $checkbox = 'This is my own device. Keep me signed in for 90 days.';

    Livewire::test(SignIn::class)->set('codeSent', true)->assertSee($checkbox);

    useMobileBuild();

    Livewire::test(SignIn::class)->set('codeSent', true)->assertDontSee($checkbox);
});

it('trusts the browser for 90 days when the box is ticked', function () {
    $user = User::factory()->create();

    signInOnWeb($user, keepSignedIn: true);

    $cookie = Cookie::queued(TrustedBrowser::COOKIE);

    expect($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getExpiresTime())->toBeGreaterThan(now()->addDays(89)->getTimestamp())
        ->and($cookie->getExpiresTime())->toBeLessThanOrEqual(now()->addDays(90)->addMinute()->getTimestamp());
});

it('does not trust the browser unless the box is ticked', function () {
    signInOnWeb(User::factory()->create(), keepSignedIn: false);

    expect(Cookie::hasQueued(TrustedBrowser::COOKIE))->toBeFalse();
});

it('signs a trusted browser back in, with its API token, when the session has gone', function () {
    $user = User::factory()->create();

    $this->withCookie(TrustedBrowser::COOKIE, trustedBrowserCookie($user))
        ->get(route('account'))
        ->assertOk();

    $this->assertAuthenticatedAs($user);
    expect(app(TokenStore::class)->get())->toBe('1|secret');
});

it('asks for sign-in again once the 90 days are up', function () {
    $user = User::factory()->create();

    $this->withCookie(TrustedBrowser::COOKIE, trustedBrowserCookie($user, now()->subMinute()->getTimestamp()))
        ->get(route('account'))
        ->assertRedirect(route('sign-in'));

    $this->assertGuest();
});

it('stops trusting the browser when its user no longer exists', function () {
    $this->withCookie(TrustedBrowser::COOKIE, trustedBrowserCookie(strtolower((string) Str::ulid())))
        ->get(route('account'))
        ->assertRedirect(route('sign-in'))
        ->assertCookieExpired(TrustedBrowser::COOKIE);

    $this->assertGuest();
});

it('stops trusting the browser on sign-out', function () {
    Http::fake(['api.itp.test/v1/auth/logout' => Http::response(['message' => 'Signed out.', 'data' => (object) [], 'errors' => (object) []])]);
    $user = User::factory()->create();

    $this->withCookie(TrustedBrowser::COOKIE, trustedBrowserCookie($user))
        ->post(route('sign-out'))
        ->assertRedirect(route('sign-in'))
        ->assertCookieExpired(TrustedBrowser::COOKIE);

    $this->assertGuest();
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/auth/logout')
        && $request->header('Authorization') === ['Bearer 1|secret']);
});

it('stops trusting the browser when the API rejects its token', function () {
    app(TokenStore::class)->put('1|revoked');
    Http::fake(['api.itp.test/v1/generations' => Http::response(['message' => 'Unauthenticated.', 'data' => (object) [], 'errors' => (object) []], 401)]);

    Livewire::actingAs(User::factory()->create())
        ->test(Home::class)
        ->call('refresh')
        ->assertRedirect(route('sign-in'));

    expect(Cookie::queued(TrustedBrowser::COOKIE)->getExpiresTime())->toBeLessThan(now()->getTimestamp());
});
