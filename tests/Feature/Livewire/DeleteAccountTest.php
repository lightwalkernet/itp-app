<?php

use App\Auth\TokenStore;
use App\Livewire\DeleteAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Itp\Core\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    Http::preventStrayRequests();
});

/**
 * itp-server's answer for the account endpoints: `{message, data, errors}`.
 *
 * @param  array<string, list<string>>  $errors
 */
function accountApiResponse(string $message, array $errors = [], int $status = 200): mixed
{
    return Http::response(['message' => $message, 'data' => (object) [], 'errors' => (object) $errors], $status);
}

it('explains what is deleted, for a guest too', function () {
    $this->get(route('account.delete'))
        ->assertOk()
        ->assertSeeLivewire(DeleteAccount::class)
        ->assertSee('data-room="white"', escape: false)
        ->assertSee('This deletes your account and everything in it: your personal ITPs, your record of what you’ve kept, and your settings. It can’t be undone.')
        ->assertSee('Send a code');
});

it('is not a page on the phone', function () {
    useMobileBuild()->items = [];

    $this->get(route('account.delete'))->assertNotFound();
});

it('fills in the signed-in user’s email', function () {
    Livewire::actingAs(User::factory()->withoutFirstName()->create(['email' => 'ann@example.com']))
        ->test(DeleteAccount::class)
        ->assertSet('email', 'ann@example.com');
});

it('sends a deletion code, then asks for it', function () {
    Http::fake(['api.itp.test/v1/account/deletion-code' => accountApiResponse('If that email has an account, we sent it a code.', status: 202)]);

    Livewire::test(DeleteAccount::class)
        ->set('email', ' Ann@Example.com ')
        ->call('sendCode')
        ->assertHasNoErrors()
        ->assertSet('codeSent', true)
        ->assertSee('We sent a six-digit code to ann@example.com.')
        ->assertSee('Delete my account');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.itp.test/v1/account/deletion-code'
        && $request['email'] === 'ann@example.com'
        && ! $request->hasHeader('Authorization'));
});

it('checks the email before asking the API', function () {
    Livewire::test(DeleteAccount::class)
        ->set('email', 'not an email')
        ->call('sendCode')
        ->assertHasErrors('email')
        ->assertSet('codeSent', false);

    Http::assertNothingSent();
});

it('shows the API’s answer to a wrong code, and deletes nothing', function () {
    Http::fake([
        'api.itp.test/v1/account/deletion-code' => accountApiResponse('Sent.'),
        'api.itp.test/v1/account/delete' => accountApiResponse('That code is incorrect.', ['code' => ['That code is incorrect.']], 422),
    ]);
    $user = User::factory()->create(['email' => 'ann@example.com']);

    Livewire::actingAs($user)
        ->test(DeleteAccount::class)
        ->call('sendCode')
        ->set('code', '000000')
        ->call('deleteAccount')
        ->assertHasErrors(['code' => 'That code is incorrect.'])
        ->assertSee('That code is incorrect.')
        ->assertSet('deleted', false);

    expect(Auth::id())->toBe($user->id);
});

it('says so when the API can’t be reached', function () {
    Http::fake(['api.itp.test/v1/account/deletion-code' => fn () => throw new ConnectionException('Offline')]);

    Livewire::test(DeleteAccount::class)
        ->set('email', 'ann@example.com')
        ->call('sendCode')
        ->assertHasErrors('email')
        ->assertSet('codeSent', false);
});

it('deletes the account, signs this browser out of it, and says it is done', function () {
    Http::fake([
        'api.itp.test/v1/account/deletion-code' => accountApiResponse('Sent.'),
        'api.itp.test/v1/account/delete' => accountApiResponse('Your account is deleted.'),
    ]);
    $user = User::factory()->create(['email' => 'ann@example.com']);
    app(TokenStore::class)->put('1|secret');

    Livewire::actingAs($user)
        ->test(DeleteAccount::class)
        ->call('sendCode')
        ->set('code', '123456')
        ->call('deleteAccount')
        ->assertHasNoErrors()
        ->assertSet('deleted', true)
        ->assertSee('Your account is deleted.')
        ->assertSeeHtml('<a href="/" class="btn">Back to Today</a>');

    expect(Auth::check())->toBeFalse()
        ->and(app(TokenStore::class)->get())->toBeNull();
    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.itp.test/v1/account/delete'
        && $request['email'] === 'ann@example.com'
        && $request['code'] === '123456');
});

it('leaves a different signed-in account signed in', function () {
    Http::fake([
        'api.itp.test/v1/account/deletion-code' => accountApiResponse('Sent.'),
        'api.itp.test/v1/account/delete' => accountApiResponse('Your account is deleted.'),
    ]);
    $user = User::factory()->create(['email' => 'ann@example.com']);

    Livewire::actingAs($user)
        ->test(DeleteAccount::class)
        ->set('email', 'bea@example.com')
        ->call('sendCode')
        ->set('code', '123456')
        ->call('deleteAccount')
        ->assertSet('deleted', true);

    expect(Auth::id())->toBe($user->id);
});

it('lets a guest start again with a different email', function () {
    Http::fake(['api.itp.test/v1/account/deletion-code' => accountApiResponse('Sent.')]);

    Livewire::test(DeleteAccount::class)
        ->set('email', 'ann@example.com')
        ->call('sendCode')
        ->call('useDifferentEmail')
        ->assertSet('codeSent', false)
        ->assertSee('Send a code');
});
