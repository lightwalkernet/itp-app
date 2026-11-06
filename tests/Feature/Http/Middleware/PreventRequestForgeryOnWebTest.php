<?php

use App\Http\Middleware\PreventRequestForgeryOnWeb;
use Itp\Core\Models\User;

/**
 * Laravel skips CSRF checks under a test runner, so these use the middleware with that turned
 * off. Signing out is the request: a POST that changes state.
 */
beforeEach(function () {
    app()->bind(PreventRequestForgeryOnWeb::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgeryOnWeb
    {
        protected function runningUnitTests(): bool
        {
            return false;
        }
    });
});

it('rejects a request without a CSRF token on the web build', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('sign-out'))
        ->assertStatus(419);

    $this->assertAuthenticated();
});

it('never checks CSRF tokens on the phone', function () {
    config(['itp.runtime' => 'mobile', 'sync.role' => 'client']);

    $this->actingAs(User::factory()->create())
        ->post(route('sign-out'))
        ->assertRedirect(route('sign-in'));

    $this->assertGuest();
});
