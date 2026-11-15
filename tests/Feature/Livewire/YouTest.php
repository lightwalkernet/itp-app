<?php

use App\Auth\TokenStore;
use App\Livewire\You;
use Illuminate\Support\Facades\Http;
use Itp\Core\Models\Itp;
use Itp\Core\Models\UniversalItp;
use Itp\Core\Models\User;
use Itp\Core\Models\UserSetting;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    Http::preventStrayRequests();
});

it('shows who is signed in, what they live with, and the day’s reminders', function () {
    $user = User::factory()->create(['first_name' => 'Ann', 'email' => 'ann@example.com']);
    $itp = Itp::factory()->for($user)->create(['title' => 'The quiet yes']);
    UserSetting::factory()->for($user)->livingWith($itp)->create(['why_at' => '15:15:00', 'why_reminder' => false]);

    $this->actingAs($user)
        ->get(route('you'))
        ->assertOk()
        ->assertSeeLivewire(You::class)
        ->assertSee('data-room="white"', escape: false)
        ->assertSeeInOrder([
            '<p class="t-kicker">Ann</p>',
            '<h1 class="t-room">You</h1>',
            'href="/itps" data-transition="forward" class="row">Your record',
            'href="/itps/'.$itp->id.'" data-transition="forward" class="row">Living with',
            'The quiet yes · change',
            'The day’s three reminders',
            'Morning', 'value="07:00"', 'aria-checked="true" aria-label="Morning reminder"',
            'A Quantum Why', 'value="15:15"', 'aria-checked="false" aria-label="Quantum Why"',
            'Evening', 'value="19:30"', 'aria-checked="true" aria-label="Evening reminder"',
            'The field',
            'Still mode', 'window.itpField.setStill(still)',
            'Text size', 'follows your phone',
            'href="/about" data-transition="forward" class="row">About It’s Totally Possible',
            'Signed in', 'ann@example.com',
            'action="/sign-out"',
            'Sign out',
            'Delete account',
        ], escape: false);
});

it('leads to Meet Your ITP Self until another is chosen, or says it is coming soon', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('you'))
        ->assertSee('<button type="button" class="row" aria-disabled="true">Living with', escape: false)
        ->assertSee('coming soon');

    $meetYourItpSelf = UniversalItp::factory()->base()->create(['slug' => 'meet-your-itp-self', 'short_name' => 'Meet Your ITP Self']);

    $this->actingAs($user)->get(route('you'))
        ->assertSee('href="/universal/'.$meetYourItpSelf->slug.'" data-transition="forward" class="row">Living with', escape: false)
        ->assertSee('Meet Your ITP Self · change');
});

it('turns a reminder off and on, on the user’s settings', function () {
    $user = User::factory()->create();
    $settings = UserSetting::factory()->for($user)->create();

    $you = Livewire::actingAs($user)->test(You::class)->call('toggleReminder', 'evening');

    expect($settings->fresh()->evening_reminder)->toBeFalse()
        ->and($settings->fresh()->morning_reminder)->toBeTrue();
    $you->assertSeeHtml('aria-checked="false" aria-label="Evening reminder"');

    $you->call('toggleReminder', 'evening');

    expect($settings->fresh()->evening_reminder)->toBeTrue();
});

it('keeps a reminder time', function () {
    $user = User::factory()->create();
    $settings = UserSetting::factory()->for($user)->create();

    Livewire::actingAs($user)->test(You::class)
        ->set('times.morning', '06:45')
        ->assertHasNoErrors();

    expect($settings->fresh()->morning_at)->toBe('06:45:00');
});

it('refuses a time that isn’t a time of day', function (string $time) {
    $user = User::factory()->create();
    $settings = UserSetting::factory()->for($user)->create();

    Livewire::actingAs($user)->test(You::class)
        ->set('times.why', $time)
        ->assertHasErrors('times.why');

    expect($settings->fresh()->why_at)->toBe('14:30:00');
})->with(['empty' => '', 'not a time' => 'soon', 'out of range' => '25:00']);

it('creates the settings row if sign-in somehow left none', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(You::class)
        ->assertSet('times.morning', '07:00')
        ->assertSet('reminders.why', true)
        ->call('toggleReminder', 'why');

    expect($user->settings()->sole())
        ->why_reminder->toBeFalse()
        ->morning_at->toBe('07:00:00');
});

it('ignores a reminder that doesn’t exist', function () {
    Livewire::actingAs(User::factory()->create())->test(You::class)
        ->call('toggleReminder', 'midnight')
        ->assertNotFound();
});

it('deletes the account on the web build’s page: in the app on the web, in the browser from the phone', function (string $runtime, string $link) {
    $user = User::factory()->create();
    config(['itp.web_url' => 'https://app.itp.test']);

    if ($runtime === 'mobile') {
        useMobileBuild()->items['itp.user_id'] = $user->id;
    }

    $this->actingAs($user)->get(route('you'))->assertSee($link, escape: false);
})->with([
    'web' => ['server', '<a href="/delete-account" data-transition="forward" class="btn-sec you-delete">Delete account</a>'],
    'phone' => ['mobile', '<a href="https://app.itp.test/delete-account" class="btn-sec you-delete">Delete account</a>'],
]);

it('signs out from its form', function () {
    Http::fake(['api.itp.test/v1/auth/logout' => Http::response(['message' => 'Signed out.', 'data' => (object) [], 'errors' => (object) []])]);
    $this->actingAs(User::factory()->create());
    app(TokenStore::class)->put('1|secret');

    $this->get(route('you'))->assertSee('<form method="POST" action="/sign-out">', escape: false);
    $this->post(route('sign-out'))->assertRedirect(route('sign-in'));

    $this->assertGuest();
});

it('sends the old account address to You', function () {
    $this->actingAs(User::factory()->create())
        ->get('/account')
        ->assertRedirect('/you');
});
