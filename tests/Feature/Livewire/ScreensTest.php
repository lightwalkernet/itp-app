<?php

use App\Livewire\Account;
use App\Livewire\Home;
use App\Livewire\UpdateRequired;
use Itp\Core\Models\User;

beforeEach(function () {
    $this->withoutVite();
});

it('sends guests to sign in', function (string $route) {
    $this->get(route($route))->assertRedirect(route('sign-in'));
})->with(['home', 'account', 'profile']);

it('sends a user without a first name to the profile screen on every visit', function (string $route) {
    $this->actingAs(User::factory()->withoutFirstName()->create())
        ->get(route($route))
        ->assertRedirect(route('profile'));
})->with(['home', 'account']);

it('greets the user at home, with the tab bar', function () {
    $this->actingAs(User::factory()->create(['first_name' => 'Ann']))
        ->get(route('home'))
        ->assertOk()
        ->assertSeeLivewire(Home::class)
        ->assertSee('Hello, Ann')
        ->assertSee('data-transition="tab"', escape: false);
});

/*
 * On the phone Laravel builds http://127.0.0.1 URLs for pages served from php://127.0.0.1, so an
 * absolute link leaves the app's origin: a tab can't navigate, a POST arrives as a bodyless GET.
 */
it('links between screens by path, never by absolute URL', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('home'))
        ->assertSee('href="/" data-transition="tab"', escape: false)
        ->assertSee('href="/account" data-transition="tab"', escape: false)
        ->assertDontSee('href="'.url('/'), escape: false);

    $this->get(route('account'))
        ->assertSee('action="/sign-out"', escape: false);
});

it('shows the account and a sign-out form', function () {
    $this->actingAs(User::factory()->create(['first_name' => 'Ann', 'email' => 'ann@example.com']))
        ->get(route('account'))
        ->assertOk()
        ->assertSeeLivewire(Account::class)
        ->assertSee('ann@example.com')
        ->assertSee('Sign out');
});

it('shows the update screen for each build', function (string $flag, string $text) {
    config(['itp.runtime' => $flag]);

    $this->get(route('update-required'))
        ->assertOk()
        ->assertSeeLivewire(UpdateRequired::class)
        ->assertSee($text);
})->with([
    'web' => ['server', 'Reload the page to continue.'],
    'phone' => ['mobile', 'Update it from the App Store or Google Play.'],
]);

it('turns off double-tap zoom and slides for 450ms', function () {
    $css = file_get_contents(resource_path('css/transitions.css'));

    expect($css)->toContain('html { touch-action: manipulation; }')
        ->toContain('--page-duration: 450ms;');
});
