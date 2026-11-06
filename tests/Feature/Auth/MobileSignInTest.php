<?php

use App\Auth\SecureStorageTokenStore;
use App\Auth\SessionTokenStore;
use App\Auth\TokenStore;
use App\Livewire\CompleteProfile;
use App\Livewire\SignIn;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Itp\Core\Models\Itp;
use Itp\Core\Models\User;
use Livewire\Livewire;
use Native\Mobile\SecureStorage;

/**
 * The mobile build: the phone has its own SQLite file, so sign-in writes the API's user row
 * locally, and the token lives in SecureStorage (platform.md §5).
 */
beforeEach(function () {
    Http::preventStrayRequests();
    $this->withoutVite();

    config(['itp.runtime' => 'mobile', 'sync.role' => 'client']);

    $this->keychain = new class extends SecureStorage
    {
        /** @var array<string, string> */
        public array $items = [];

        public function set(string $key, ?string $value, $accessibility = null): bool
        {
            $this->items[$key] = $value;

            return true;
        }

        public function get(string $key): ?string
        {
            return $this->items[$key] ?? null;
        }

        public function delete(string $key): bool
        {
            unset($this->items[$key]);

            return true;
        }
    };

    app()->instance(SecureStorage::class, $this->keychain);
});

/**
 * itp-server's verify-code answer, as UserResource shapes it.
 *
 * @param  array<string, mixed>  $user
 */
function mobileSignInResponse(array $user, bool $needsProfile = false): mixed
{
    return Http::response(['message' => 'Signed in.', 'errors' => (object) [], 'data' => [
        'user' => $user + ['email_verified_at' => '2026-11-06T05:57:00.000000Z', 'created_at' => '2026-11-04T09:00:00.000000Z', 'updated_at' => '2026-11-06T05:57:00.000000Z'],
        'token' => '1|secret',
        'needs_profile' => $needsProfile,
    ]]);
}

it('keeps the token in SecureStorage on the phone and in the session on the web', function () {
    expect(app(TokenStore::class))->toBeInstanceOf(SecureStorageTokenStore::class);

    config(['itp.runtime' => 'server']);

    expect(app(TokenStore::class))->toBeInstanceOf(SessionTokenStore::class);
});

it('writes the API\'s user row to the phone, then signs in', function () {
    $id = strtolower((string) Str::ulid());
    Http::fake(['api.itp.test/v1/auth/verify-code' => mobileSignInResponse(['id' => $id, 'email' => 'ann@example.com', 'first_name' => 'Ann'])]);

    Livewire::test(SignIn::class)
        ->set('email', 'ann@example.com')
        ->set('codeSent', true)
        ->set('code', '123456')
        ->call('verifyCode')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    $user = User::find($id);
    expect($user)->not->toBeNull()
        ->and($user->email)->toBe('ann@example.com')
        ->and($user->first_name)->toBe('Ann')
        ->and($user->sync_dirty)->toBeFalse()
        ->and($user->updated_at->toIso8601String())->toBe('2026-11-06T05:57:00+00:00');
    $this->assertAuthenticatedAs($user);
    expect($this->keychain->items)->toBe(['itp.api_token' => '1|secret', 'itp.user_id' => $id]);
    Http::assertSent(fn (Request $request) => str_starts_with($request['device_name'], 'Mobile: '));
});

it('updates the phone\'s copy when the same user signs in again', function () {
    $user = User::factory()->create(['email' => 'ann@example.com', 'first_name' => 'Old']);
    Http::fake(['api.itp.test/v1/auth/verify-code' => mobileSignInResponse(['id' => $user->id, 'email' => 'ann@example.com', 'first_name' => 'Ann'])]);

    Livewire::test(SignIn::class)
        ->set('email', 'ann@example.com')
        ->set('code', '123456')
        ->call('verifyCode')
        ->assertRedirect(route('home'));

    expect(User::count())->toBe(1)
        ->and($user->fresh()->first_name)->toBe('Ann');
});

it('saves a new first name on the phone after the API accepts it', function () {
    $user = User::factory()->withoutFirstName()->create();
    $this->keychain->items['itp.api_token'] = '1|secret';
    Http::fake(['api.itp.test/v1/auth/complete-profile' => Http::response(['message' => 'Profile updated.', 'errors' => (object) [], 'data' => [
        'user' => ['id' => $user->id, 'email' => $user->email, 'first_name' => 'Ann'],
        'needs_profile' => false,
    ]])]);

    Livewire::actingAs($user)
        ->test(CompleteProfile::class)
        ->set('first_name', 'Ann')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    expect($user->fresh()->first_name)->toBe('Ann');
    Http::assertSent(fn (Request $request) => $request->header('Authorization') === ['Bearer 1|secret']);
});

it('wipes the user\'s rows and token from the phone on sign-out', function () {
    Http::fake(['api.itp.test/v1/auth/logout' => Http::response(['message' => 'Signed out.', 'data' => (object) [], 'errors' => (object) []])]);
    $user = User::factory()->create();
    Itp::factory()->for($user)->create();
    $this->keychain->items = ['itp.api_token' => '1|secret', 'itp.user_id' => $user->id];

    $this->actingAs($user)->post(route('sign-out'))->assertRedirect(route('sign-in'));

    $this->assertGuest();
    expect($this->keychain->items)->toBe([])
        ->and(User::withTrashed()->count())->toBe(0)
        ->and(Itp::withTrashed()->count())->toBe(0);
});

it('stays signed in from the keychain when the session is gone', function () {
    $user = User::factory()->create();
    $this->keychain->items = ['itp.api_token' => '1|secret', 'itp.user_id' => $user->id];

    $this->get(route('home'))->assertOk();
    $this->assertAuthenticatedAs($user);

    $this->get(route('sign-in'))->assertRedirect(route('home'));
});

it('asks for sign-in again when the keychain\'s user is no longer on the phone', function () {
    $this->keychain->items = ['itp.api_token' => '1|secret', 'itp.user_id' => strtolower((string) Str::ulid())];

    $this->get(route('home'))->assertRedirect(route('sign-in'));

    $this->assertGuest();
    expect($this->keychain->items)->toBe([]);
});

it('needs both the token and the user to restore a sign-in', function (array $keychain) {
    $user = User::factory()->create();
    $this->keychain->items = array_map(fn (string $value) => $value === 'user' ? $user->id : $value, $keychain);

    $this->get(route('home'))->assertRedirect(route('sign-in'));

    $this->assertGuest();
})->with([
    'no token' => [['itp.user_id' => 'user']],
    'no user' => [['itp.api_token' => '1|secret']],
]);

it('never restores a sign-in from the keychain on the web build', function () {
    config(['itp.runtime' => 'server', 'sync.role' => 'server']);
    $user = User::factory()->create();
    $this->keychain->items = ['itp.api_token' => '1|secret', 'itp.user_id' => $user->id];

    $this->get(route('home'))->assertRedirect(route('sign-in'));

    $this->assertGuest();
});

it('goes home after a new user saves their first name', function () {
    $id = strtolower((string) Str::ulid());
    $user = ['id' => $id, 'email' => 'ann@example.com', 'first_name' => null];
    Http::fake([
        'api.itp.test/v1/auth/verify-code' => mobileSignInResponse($user, needsProfile: true),
        'api.itp.test/v1/auth/complete-profile' => Http::response(['message' => 'Profile updated.', 'errors' => (object) [], 'data' => [
            'user' => ['first_name' => 'Ann'] + $user,
            'needs_profile' => false,
        ]]),
    ]);

    // One app serves every request, as NativePHP's persistent runtime does on the phone.
    Livewire::test(SignIn::class)
        ->set('email', 'ann@example.com')
        ->set('code', '123456')
        ->call('verifyCode')
        ->assertRedirect(route('profile'));

    Livewire::test(CompleteProfile::class)
        ->set('first_name', 'Ann')
        ->call('save')
        ->assertRedirect(route('home'));

    $this->get(route('home'))->assertOk();
    $this->get(route('profile'))->assertRedirect(route('home'));
});
