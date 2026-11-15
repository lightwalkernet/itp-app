<?php

use App\Livewire\Home;
use App\Livewire\UpdateRequired;
use App\Livewire\You;
use Itp\Core\Models\Itp;
use Itp\Core\Models\UniversalItp;
use Itp\Core\Models\User;

beforeEach(function () {
    $this->withoutVite();
});

it('sends guests to sign in', function (string $route) {
    $this->get(route($route))->assertRedirect(route('sign-in'));
})->with(['you', 'profile', 'itps.create']);

it('sends a user without a first name to the profile screen on every visit', function (string $route) {
    $this->actingAs(User::factory()->withoutFirstName()->create())
        ->get(route($route))
        ->assertRedirect(route('profile'));
})->with(['home', 'you', 'itps.index', 'itps.create']);

it('greets the user at home, with the tab bar', function () {
    $this->travelTo('2026-11-16 09:00:00');

    $this->actingAs(User::factory()->create(['first_name' => 'Ann']))
        ->get(route('home'))
        ->assertOk()
        ->assertSeeLivewire(Home::class)
        ->assertSee('Living with')
        ->assertSee('data-transition="tab"', escape: false);
});

/*
 * On the phone Laravel builds http://127.0.0.1 URLs for pages served from php://127.0.0.1, so an
 * absolute link leaves the app's origin: a tab can't navigate, a POST arrives as a bodyless GET.
 */
it('links between screens by path, never by absolute URL', function () {
    $user = User::factory()->create();
    $itp = Itp::factory()->for($user)->create();
    $universal = UniversalItp::factory()->preview()->create();
    UniversalItp::factory()->base()->create(['slug' => 'meet-your-itp-self']);

    $this->get(route('home'))
        ->assertSee('href="/sign-in" data-transition="forward"', escape: false)
        ->assertSee('href="/universal/meet-your-itp-self" data-transition="forward"', escape: false)
        ->assertDontSee('href="'.url('/'), escape: false);

    $this->actingAs($user);

    $this->get(route('home'))
        ->assertSee('href="/" data-transition="tab"', escape: false)
        ->assertSee('href="/itps" data-transition="tab"', escape: false)
        ->assertSee('href="/you" data-transition="tab"', escape: false)
        ->assertSee('href="/universal/meet-your-itp-self" data-transition="forward"', escape: false)
        ->assertDontSee('href="'.url('/'), escape: false);

    $this->get(route('you'))
        ->assertSee('action="/sign-out"', escape: false)
        ->assertSee('href="/itps" data-transition="forward"', escape: false)
        ->assertSee('href="/universal/meet-your-itp-self" data-transition="forward"', escape: false)
        ->assertSee('href="/about" data-transition="forward"', escape: false)
        ->assertSee('href="/delete-account" data-transition="forward"', escape: false)
        ->assertDontSee('href="'.url('/'), escape: false);

    $this->get(route('itps.index', ['tab' => 'personal']))
        ->assertSee('href="/itps/'.$itp->id.'" data-transition="forward"', escape: false)
        ->assertSee('href="/itps/create" data-transition="forward"', escape: false)
        ->assertDontSee('href="'.url('/'), escape: false);

    $this->get(route('itps.index'))
        ->assertSee('href="/universal/'.$universal->slug.'" data-transition="forward"', escape: false)
        ->assertDontSee('href="'.url('/'), escape: false);

    $this->get(route('itps.create'))
        ->assertSee('href="/" data-transition="back"', escape: false)
        ->assertDontSee('href="'.url('/'), escape: false);

    foreach ([route('itps.show', $itp), route('universals.show', $universal)] as $screen) {
        $this->get($screen)
            ->assertSee('href="/itps" data-transition="back"', escape: false)
            ->assertDontSee('href="'.url('/'), escape: false);
    }
});

it('shows You, with the account and a sign-out form', function () {
    $this->actingAs(User::factory()->create(['first_name' => 'Ann', 'email' => 'ann@example.com']))
        ->get(route('you'))
        ->assertOk()
        ->assertSeeLivewire(You::class)
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
