<?php

use App\Auth\TokenStore;
use App\Livewire\CompleteProfile;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Itp\Core\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    Http::preventStrayRequests();
    $this->withoutVite();
});

it('saves the first name through the API, then goes home', function () {
    $user = User::factory()->withoutFirstName()->create();
    app(TokenStore::class)->put('1|secret');
    Http::fake(['api.itp.test/v1/auth/complete-profile' => function (Request $request) use ($user) {
        // itp-server writes the shared database; the web build only reads it.
        $user->update(['first_name' => $request['first_name']]);

        return Http::response(['message' => 'Profile updated.', 'errors' => (object) [], 'data' => [
            'user' => ['id' => $user->id, 'first_name' => $request['first_name']],
            'needs_profile' => false,
        ]]);
    }]);

    Livewire::actingAs($user)
        ->test(CompleteProfile::class)
        ->set('first_name', '  Ann ')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    Http::assertSent(fn (Request $request) => $request['first_name'] === 'Ann'
        && $request->header('Authorization') === ['Bearer 1|secret']);
});

it('requires a first name before calling the API', function () {
    Livewire::actingAs(User::factory()->withoutFirstName()->create())
        ->test(CompleteProfile::class)
        ->call('save')
        ->assertHasErrors(['first_name' => 'required']);

    Http::assertNothingSent();
});

it('signs out locally when the API says the token is gone', function () {
    $user = User::factory()->withoutFirstName()->create();
    app(TokenStore::class)->put('1|revoked');
    Http::fake(['api.itp.test/v1/auth/complete-profile' => Http::response(['message' => 'Unauthenticated.', 'data' => (object) [], 'errors' => (object) []], 401)]);

    Livewire::actingAs($user)
        ->test(CompleteProfile::class)
        ->set('first_name', 'Ann')
        ->call('save')
        ->assertRedirect(route('sign-in'));

    $this->assertGuest();
    expect(app(TokenStore::class)->get())->toBeNull();
});

it('skips the screen once the profile is complete', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(CompleteProfile::class)
        ->assertRedirect(route('home'));
});
