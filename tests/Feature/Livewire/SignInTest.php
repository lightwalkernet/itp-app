<?php

use App\Auth\TokenStore;
use App\Livewire\SignIn;
use App\Support\GuestChoice;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Http;
use Itp\Core\Enums\LivingWithType;
use Itp\Core\Models\UniversalItp;
use Itp\Core\Models\User;
use Itp\Core\Models\UserSetting;
use Livewire\Livewire;

beforeEach(function () {
    Http::preventStrayRequests();
    $this->withoutVite();
});

/**
 * itp-server's verify-code answer for the user.
 */
function signedInResponse(User $user, bool $needsProfile): mixed
{
    return Http::response(['message' => 'Signed in.', 'errors' => (object) [], 'data' => [
        'user' => ['id' => $user->id, 'email' => $user->email, 'first_name' => $user->first_name],
        'token' => '1|secret',
        'needs_profile' => $needsProfile,
    ]]);
}

it('renders the sign-in screen for guests', function () {
    $this->get(route('sign-in'))->assertOk()->assertSeeLivewire(SignIn::class);
});

it('asks the API for a code and moves to the code step', function () {
    Http::fake(['api.itp.test/v1/auth/send-code' => Http::response(['message' => 'We sent a sign-in code to that email.', 'data' => (object) [], 'errors' => (object) []])]);

    Livewire::test(SignIn::class)
        ->set('email', '  Ann@Example.com ')
        ->call('sendCode')
        ->assertHasNoErrors()
        ->assertSet('codeSent', true)
        ->assertSee('ann@example.com');

    Http::assertSent(fn (Request $request) => $request['email'] === 'ann@example.com');
});

it('signs in with a valid code and keeps the token', function () {
    $user = User::factory()->create(['email' => 'ann@example.com']);
    Http::fake(['api.itp.test/v1/auth/verify-code' => signedInResponse($user, false)]);

    Livewire::test(SignIn::class)
        ->set('email', 'ann@example.com')
        ->set('codeSent', true)
        ->set('code', '123456')
        ->call('verifyCode')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
    expect(app(TokenStore::class)->get())->toBe('1|secret');
    Http::assertSent(fn (Request $request) => str_starts_with($request['device_name'], 'Web: '));
});

it('leaves the settings to the shared database on the web', function () {
    $user = User::factory()->create(['email' => 'ann@example.com']);
    $settings = UserSetting::factory()->for($user)->create(['why_at' => '14:30:00']);
    Http::fake(['api.itp.test/v1/auth/verify-code' => Http::response(['message' => 'Signed in.', 'errors' => (object) [], 'data' => [
        'user' => ['id' => $user->id, 'email' => $user->email, 'first_name' => $user->first_name],
        'token' => '1|secret',
        'needs_profile' => false,
        'settings' => ['id' => $settings->id, 'user_id' => $user->id, 'why_at' => '09:00:00'],
    ]])]);

    Livewire::test(SignIn::class)
        ->set('email', 'ann@example.com')
        ->set('code', '123456')
        ->call('verifyCode')
        ->assertRedirect(route('home'));

    expect($settings->fresh()->why_at)->toBe('14:30:00');
});

it('makes the ITP chosen as a guest the user\'s own at sign-in, then forgets the guest\'s', function () {
    $user = User::factory()->create(['email' => 'ann@example.com']);
    $settings = UserSetting::factory()->for($user)->create();
    $itp = UniversalItp::factory()->preview()->create(['slug' => 'awaken-wonder']);
    Http::fake(['api.itp.test/v1/auth/verify-code' => signedInResponse($user, false)]);

    Livewire::withCookie(GuestChoice::COOKIE, 'awaken-wonder')
        ->test(SignIn::class)
        ->set('email', 'ann@example.com')
        ->set('code', '123456')
        ->call('verifyCode')
        ->assertRedirect(route('home'));

    expect($settings->fresh())
        ->living_with_type->toBe(LivingWithType::Universal)
        ->living_with_id->toBe($itp->id);
    expect(Cookie::queued(GuestChoice::COOKIE)?->isCleared())->toBeTrue();
});

it('keeps what a user already lives with over a guest\'s choice at sign-in', function () {
    $user = User::factory()->create(['email' => 'ann@example.com']);
    $own = UniversalItp::factory()->create();
    $settings = UserSetting::factory()->for($user)->livingWith($own)->create();
    UniversalItp::factory()->preview()->create(['slug' => 'awaken-wonder']);
    Http::fake(['api.itp.test/v1/auth/verify-code' => signedInResponse($user, false)]);

    Livewire::withCookie(GuestChoice::COOKIE, 'awaken-wonder')
        ->test(SignIn::class)
        ->set('email', 'ann@example.com')
        ->set('code', '123456')
        ->call('verifyCode')
        ->assertRedirect(route('home'));

    expect($settings->fresh()->living_with_id)->toBe($own->id);
    expect(Cookie::queued(GuestChoice::COOKIE)?->isCleared())->toBeTrue();
});

it('sends a user without a first name to the profile screen', function () {
    $user = User::factory()->withoutFirstName()->create();
    Http::fake(['api.itp.test/v1/auth/verify-code' => signedInResponse($user, true)]);

    Livewire::test(SignIn::class)
        ->set('email', $user->email)
        ->set('code', '123456')
        ->call('verifyCode')
        ->assertRedirect(route('profile'));
});

it('shows the API\'s message for a wrong code and stays signed out', function () {
    Http::fake(['api.itp.test/v1/auth/verify-code' => Http::response([
        'message' => 'That code is incorrect.',
        'data' => (object) [],
        'errors' => ['code' => ['That code is incorrect.']],
    ], 422)]);

    Livewire::test(SignIn::class)
        ->set('email', 'ann@example.com')
        ->set('codeSent', true)
        ->set('code', '000000')
        ->call('verifyCode')
        ->assertHasErrors('code')
        ->assertSee('That code is incorrect.');

    $this->assertGuest();
});

it('validates the email and code before calling the API', function () {
    Livewire::test(SignIn::class)
        ->set('email', 'not-an-email')
        ->call('sendCode')
        ->assertHasErrors(['email' => 'email'])
        ->set('code', '12')
        ->call('verifyCode')
        ->assertHasErrors(['code' => 'digits']);

    Http::assertNothingSent();
});

it('goes to the update screen when the app is too old', function () {
    Http::fake(['api.itp.test/v1/*' => Http::response([
        'message' => 'This version of the app needs an update.',
        'data' => ['min_app_version' => '2.0.0'],
        'errors' => ['app_version' => ['Version 1.4.2 is no longer supported.']],
    ], 426)]);

    Livewire::test(SignIn::class)
        ->set('email', 'ann@example.com')
        ->call('sendCode')
        ->assertRedirect(route('update-required'));
});

it('says so when the API cannot be reached', function () {
    Http::fake(['api.itp.test/v1/*' => fn () => throw new ConnectionException('Offline')]);

    Livewire::test(SignIn::class)
        ->set('email', 'ann@example.com')
        ->call('sendCode')
        ->assertHasErrors('email')
        ->assertSet('codeSent', false)
        ->assertSee("We can't reach it's totally possible right now.");
});

it('goes back to the email step', function () {
    Livewire::test(SignIn::class)
        ->set('codeSent', true)
        ->set('code', '123')
        ->call('useDifferentEmail')
        ->assertSet('codeSent', false)
        ->assertSet('code', '');
});

it('sends signed-in users past the sign-in screen', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('sign-in'))
        ->assertRedirect(route('home'));
});
