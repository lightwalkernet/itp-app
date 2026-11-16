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

it('greets the user at home, with the bar', function () {
    $this->travelTo('2026-11-16 09:00:00');

    $this->actingAs(User::factory()->create(['first_name' => 'Ann']))
        ->get(route('home'))
        ->assertOk()
        ->assertSeeLivewire(Home::class)
        ->assertSee('Choose your ITP')
        ->assertSee('<nav class="app-tabbar" aria-label="Main">', escape: false);
});

it('draws the menu as three links by path: Your ITPs, the logo for Today, and You', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('itps.index'))
        ->assertOk()
        ->assertSeeInOrder([
            '<nav class="app-tabbar" aria-label="Main">',
            '<a href="/itps" data-transition="tab" data-tab-match="/itps /universal" class="app-tabbar__side app-tabbar__side--start" aria-label="Your ITPs">',
            '<a href="/" data-transition="tab" data-tab-match="/ /today" class="app-tabbar__logo" aria-label="Today">',
            'class="app-tabbar__glow"',
            'class="app-tabbar__mark"',
            '<a href="/you" data-transition="tab" data-tab-match="/you /about /delete-account" class="app-tabbar__side app-tabbar__side--end" aria-label="You">',
            '</nav>',
        ], escape: false)
        ->assertDontSee('Return')
        ->assertDontSee('return-sheet', escape: false)
        ->assertDontSee('href="'.url('/'), escape: false);
});

it('sends a guest\'s person icon to sign-in', function () {
    $this->get(route('itps.index'))
        ->assertOk()
        ->assertSee('<a href="/sign-in" data-transition="tab" data-tab-match="/sign-in" class="app-tabbar__side app-tabbar__side--end" aria-label="Sign in">', escape: false);
});

it('leaves the menu\'s current page to transitions.js, and hides it where the page says so', function () {
    $js = file_get_contents(resource_path('js/transitions.js'));
    $css = file_get_contents(resource_path('css/bar.css'));

    expect($js)->toContain("t.setAttribute('aria-current', 'page')")
        ->and($css)->toContain(':root[data-bar="hidden"] .app-tabbar {')
        ->toContain('.app-tabbar__logo[aria-current="page"] .app-tabbar__glow {');
});

/*
 * On the phone Laravel builds http://127.0.0.1 URLs for pages served from php://127.0.0.1, so an
 * absolute link leaves the app's origin: a tab can't navigate, a POST arrives as a bodyless GET.
 */
it('links between screens by path, never by absolute URL', function () {
    $user = User::factory()->create();
    $itp = Itp::factory()->for($user)->create();
    $universal = UniversalItp::factory()->preview()->create();

    $this->get(route('home'))
        ->assertSee('href="/sign-in" data-transition="forward"', escape: false)
        ->assertSee('href="/itps" data-transition="forward"', escape: false)
        ->assertDontSee('href="'.url('/'), escape: false);

    $this->actingAs($user);

    $this->get(route('home'))
        ->assertSee('href="/" data-transition="tab"', escape: false)
        ->assertSee('href="/itps" data-transition="tab"', escape: false)
        ->assertSee('href="/you" data-transition="tab"', escape: false)
        ->assertSee('href="/itps" data-transition="forward"', escape: false)
        ->assertDontSee('href="'.url('/'), escape: false);

    $this->get(route('you'))
        ->assertSee('action="/sign-out"', escape: false)
        ->assertSee('href="/itps" data-transition="forward"', escape: false)
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
