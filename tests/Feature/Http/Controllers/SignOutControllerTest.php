<?php

use App\Auth\TokenStore;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Itp\Core\Models\User;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('revokes the token on the server, then signs out', function () {
    Http::fake(['api.itp.test/v1/auth/logout' => Http::response(['message' => 'Signed out.', 'data' => (object) [], 'errors' => (object) []])]);
    $this->actingAs(User::factory()->create());
    app(TokenStore::class)->put('1|secret');

    $this->post(route('sign-out'))->assertRedirect(route('sign-in'));

    $this->assertGuest();
    expect(app(TokenStore::class)->get())->toBeNull();
    Http::assertSent(fn (Request $request) => $request->header('Authorization') === ['Bearer 1|secret']);
});

it('still signs out when the API cannot be reached', function () {
    Http::fake(['api.itp.test/v1/auth/logout' => fn () => throw new ConnectionException('Offline')]);
    $this->actingAs(User::factory()->create());
    app(TokenStore::class)->put('1|secret');

    $this->post(route('sign-out'))->assertRedirect(route('sign-in'));

    $this->assertGuest();
});
