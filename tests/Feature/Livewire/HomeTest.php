<?php

use App\Auth\TokenStore;
use App\Livewire\Home;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Itp\Core\Enums\EntryKind;
use Itp\Core\Enums\LivingWithType;
use Itp\Core\Models\Entry;
use Itp\Core\Models\Itp;
use Itp\Core\Models\UniversalItp;
use Itp\Core\Models\User;
use Itp\Core\Models\UserSetting;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Today (brief 1 §3): one card that follows the device's hour and what was kept on the app day.
 * The clock is frozen at noon UTC on Monday 16 November 2026; the device's own hour and day arrive
 * through setClock(), as resources/js/clock.js sends them.
 */
beforeEach(function () {
    Http::preventStrayRequests();
    $this->withoutVite();
    $this->travelTo('2026-11-16 12:00:00');
});

/**
 * itp-server's list of the user's generations, newest first.
 *
 * @param  list<array{id: string, status: string}>  $generations
 */
function generationsResponse(array $generations): mixed
{
    return Http::response(['message' => 'OK', 'errors' => (object) [], 'data' => ['generations' => array_map(
        fn (array $generation) => $generation + ['created_at' => '2026-11-07T09:00:00Z', 'updated_at' => '2026-11-07T09:00:00Z', 'itp_id' => null],
        $generations,
    )]]);
}

/**
 * A user living with a universal ITP whose one Quantum Why is the day's Why.
 *
 * @param  array<string, mixed>  $settings
 */
function userLivingWithQuietYes(array $settings = []): User
{
    $itp = UniversalItp::factory()->create(['slug' => 'the-quiet-yes', 'short_name' => 'The Quiet Yes', 'quantum_whys' => ['Why is the step so warm at five o’clock?']]);

    return UserSetting::factory()->livingWith($itp)->create($settings)->user;
}

/**
 * Today for the user at the device's hour, on the app day.
 */
function todayAt(?User $user, int $hour, int $minute = 0, string $date = '2026-11-16'): Testable
{
    $test = $user === null ? Livewire::test(Home::class) : Livewire::actingAs($user)->test(Home::class);

    return $test->call('setClock', $hour, $date, $minute);
}

/**
 * Something the user kept on the app day.
 */
function kept(User $user, EntryKind $kind, string $body, string $date = '2026-11-16'): Entry
{
    return Entry::factory()->for($user)->forDate($date)->state(['kind' => $kind, 'body' => $body])->create();
}

it('asks the user to enter first in the morning', function () {
    $user = userLivingWithQuietYes();

    todayAt($user, 7)
        ->assertSeeHtml('data-card="morning-enter"')
        ->assertSeeHtmlInOrder(['Living with', 'The Quiet Yes', 'This morning', 'The Quiet Yes', 'Enter it first. Three minutes with the light, then one question.', 'Enter', 'Something else today?', 'Your ITPs'])
        ->assertSeeHtml('href="/universal/the-quiet-yes" data-transition="forward"')
        ->assertSeeHtml('href="/itps" data-transition="forward"');
});

it('counts entering from Today as entering, then asks the morning question', function () {
    $user = userLivingWithQuietYes();

    $today = todayAt($user, 7)->call('enter');

    $today->assertDispatched('today-navigate', url: '/universal/the-quiet-yes', direction: 'forward')
        ->call('setClock', 10, '2026-11-16', 0)
        ->assertSeeHtml('data-card="morning-ask"')
        ->assertSeeHtmlInOrder(['In its afterglow', 'What does my ITP Self want today?', 'Speak it or write it. A sentence is enough.', 'Begin', 'Or sit a moment longer', 'Breathe 11'])
        ->assertSeeHtml('href="/today/morning" data-transition="forward"');
    expect(session('entered:2026-11-16'))->toBeTrue();
});

it('quotes the morning entry once it is kept, and fills the morning point', function () {
    $user = userLivingWithQuietYes();
    kept($user, EntryKind::Morning, 'To move slowly today.');

    todayAt($user, 10)
        ->assertSeeHtml('data-card="morning-kept"')
        ->assertSeeHtmlInOrder(['This morning', '“To move slowly today.”', 'Carry it lightly. The day will meet you.', 'Breathe 11 · 2 min', 'Add one aligned step, if you wish'])
        ->assertSeeHtml('href="/today/step" data-transition="forward"')
        ->assertSeeHtml('class="arc__pt arc__pt--done" data-point="morning"')
        ->assertSeeHtml('class="arc__pt" data-point="evening"');
});

it('offers the way back to centre in the afternoon, before the day’s Why arrives', function () {
    $user = userLivingWithQuietYes(['why_at' => '16:00:00']);

    todayAt($user, 15)
        ->assertSeeHtml('data-card="afternoon-return"')
        ->assertSeeHtmlInOrder(['Whenever you need it', 'Return to alignment', 'The day pulls. Here is the way back to centre.', 'Breathe 11', 'The Quiet Yes · 2 min', 'Return to Coherence', 'coming soon', 'Add one aligned step, if you wish'])
        ->assertDontSee('Why is the step so warm');
});

it('hands back the morning entry in the afternoon', function () {
    $user = userLivingWithQuietYes(['why_at' => '16:00:00']);
    kept($user, EntryKind::Morning, 'To move slowly today.');

    todayAt($user, 15)->assertSee('This morning: “To move slowly today.”');
});

it('shows the day’s Quantum Why once its time has come', function () {
    $user = userLivingWithQuietYes(['why_at' => '14:30:00']);

    todayAt($user, 14, 29)->assertSeeHtml('data-card="afternoon-return"');
    todayAt($user, 15)
        ->assertSeeHtml('data-card="afternoon-why"')
        ->assertSeeHtmlInOrder(['This afternoon, a Quantum Why', '“Why is the step so warm at five o’clock?”', 'Nothing to answer. Just carry it, and see what the day does with it.']);
});

it('links Return to Coherence once the primer is in the content', function () {
    $user = userLivingWithQuietYes();
    UniversalItp::factory()->primer()->create(['slug' => 'return-to-coherence']);

    todayAt($user, 15)
        ->assertSeeHtml('href="/universal/return-to-coherence" data-transition="forward"')
        ->assertSee('primer · 2 min');
});

it('asks what shifted in the evening, handing back the afternoon’s Why', function () {
    $user = userLivingWithQuietYes();

    todayAt($user, 19)
        ->assertSeeHtml('data-card="evening-ask"')
        ->assertSeeHtmlInOrder(['Evening reflection', 'What shifted today?', 'This afternoon it asked: “Why is the step so warm at five o’clock?” Did you notice anything?', 'Reflect', 'Not tonight', 'that’s allowed'])
        ->assertSeeHtml('href="/today/evening" data-transition="forward"');
});

it('asks for the evidence once the evening is kept', function () {
    $user = userLivingWithQuietYes();
    kept($user, EntryKind::Evening, 'I stopped at five.');

    todayAt($user, 19)
        ->assertSeeHtml('data-card="evening-evidence"')
        ->assertSeeHtmlInOrder(['Celebrate the evidence', 'What are you proud of today?', 'Glimmers, synchronicities, courage. It all counts.', 'Add to the record', 'Or enter', 'The Quiet Yes', '3 min'])
        ->assertSeeHtml('href="/today/evidence" data-transition="forward"')
        ->assertSeeHtml('class="arc__pt arc__pt--done" data-point="evening"');
});

it('closes the day once the evidence is kept', function () {
    $user = userLivingWithQuietYes();
    kept($user, EntryKind::Evening, 'I stopped at five.');
    kept($user, EntryKind::Evidence, 'My daughter asked me to walk with her.');

    todayAt($user, 19)
        ->assertSeeHtml('data-card="complete"')
        ->assertSeeHtmlInOrder(['Today is complete', '“My daughter asked me to walk with her.”', 'Kept in your record.', 'Enter — The Quiet Yes', 'Open', 'your record'])
        ->assertSeeHtml('href="/you" data-transition="forward"');
});

it('asks what shifted before sleep when the evening wasn’t kept', function () {
    $user = userLivingWithQuietYes();

    todayAt($user, 22)
        ->assertSeeHtml('data-card="evening-ask"')
        ->assertSeeHtmlInOrder(['Before sleep', 'What shifted today?'])
        ->assertSeeHtml('class="arc__sun arc__sun--hidden"');
});

it('lets the day go quiet at night once the evening is kept', function () {
    $user = userLivingWithQuietYes();
    kept($user, EntryKind::Evening, 'I stopped at five.');

    todayAt($user, 22)
        ->assertSeeHtml('data-card="night-done"')
        ->assertSeeHtmlInOrder(['Before sleep', 'The Quiet Yes', 'Let the day go quiet before it closes.', 'Enter', 'Open', 'your record']);
});

it('keeps an empty evening for “not tonight”, with the day’s Why, and lets the day close', function () {
    $user = userLivingWithQuietYes();

    todayAt($user, 19)
        ->call('notTonight')
        ->assertSeeHtml('data-card="night-done"')
        ->assertSeeHtml('class="arc__pt" data-point="evening"');

    $entry = $user->entries()->sole();
    expect($entry->kind)->toBe(EntryKind::Evening)
        ->and($entry->for_date->toDateString())->toBe('2026-11-16')
        ->and($entry->body)->toBe('—')
        ->and($entry->why)->toBe('Why is the step so warm at five o’clock?')
        ->and($entry->living_with_type)->toBe(LivingWithType::Universal);
});

it('counts half past midnight as the day before', function () {
    $this->travelTo('2026-11-17 00:30:00');
    $user = userLivingWithQuietYes();
    kept($user, EntryKind::Evening, 'I stopped at five.', date: '2026-11-16');

    Livewire::actingAs($user)->test(Home::class)
        ->assertSet('date', '2026-11-16')
        ->assertSeeHtml('data-card="night-done"');
});

it('takes the hour and day from the device, not the server', function () {
    $user = userLivingWithQuietYes();

    Livewire::actingAs($user)->test(Home::class)
        ->assertSeeHtml('data-card="afternoon-return"')
        ->call('setClock', 19, '2026-11-16', 5)
        ->assertSet('hour', 19)
        ->assertSeeHtml('data-card="evening-ask"');
});

it('ignores a clock no device could have', function (mixed $hour, mixed $date) {
    $user = userLivingWithQuietYes();

    Livewire::actingAs($user)->test(Home::class)
        ->call('setClock', $hour, $date, 0)
        ->assertSet('hour', 12)
        ->assertSet('date', '2026-11-16');
})->with([
    'hour out of range' => [25, '2026-11-16'],
    'hour as text' => ['19', '2026-11-16'],
    'not a date' => [19, 'yesterday'],
    'impossible date' => [19, '2026-02-30'],
    'days away' => [19, '2026-11-20'],
]);

it('lives with Meet Your ITP Self until the user chooses, and shows guests its card every hour', function (int $hour) {
    UniversalItp::factory()->base()->create(['slug' => 'meet-your-itp-self', 'short_name' => 'Meet Your ITP Self']);

    todayAt(null, $hour)
        ->assertSeeHtml('data-card="guest"')
        ->assertSeeHtmlInOrder(['Living with', 'Meet Your ITP Self', 'This morning', 'Meet Your ITP Self', 'Four Universals are open before you sign in. Your own ITP comes after.', 'Enter', 'Sign in — ', 'email and a code'])
        ->assertSeeHtml('href="/universal/meet-your-itp-self" data-transition="forward"')
        ->assertSeeHtml('href="/sign-in" data-transition="forward"');
})->with([7, 15, 22]);

it('shows Meet Your ITP Self as coming soon while it isn’t in the content', function (bool $signedIn) {
    todayAt($signedIn ? User::factory()->create() : null, 7)
        ->assertSee('Meet Your ITP Self')
        ->assertSeeHtml('<span class="living__name">Meet Your ITP Self</span>')
        ->assertSeeHtmlInOrder(['<button type="button" class="btn" disabled="disabled">Enter', '<small>coming soon</small>'])
        ->assertDontSeeHtml('wire:click="enter"');
})->with(['guest' => false, 'signed in' => true]);

it('lives with Meet Your ITP Self when the user has no settings yet', function () {
    UniversalItp::factory()->base()->create(['slug' => 'meet-your-itp-self', 'short_name' => 'Meet Your ITP Self']);

    todayAt(User::factory()->create(), 7)
        ->assertSeeHtml('data-card="morning-enter"')
        ->assertSeeHtml('<a class="living__name" href="/universal/meet-your-itp-self" data-transition="forward">Meet Your ITP Self</a>');
});

it('links a personal ITP the user lives with to its own page', function () {
    $user = User::factory()->create();
    $itp = Itp::factory()->for($user)->create(['title' => 'The Quiet Yes']);
    UserSetting::factory()->livingWith($itp)->create();

    todayAt($user, 7)
        ->call('enter')
        ->assertDispatched('today-navigate', url: '/itps/'.$itp->id, direction: 'forward')
        ->assertSeeHtml('<a class="living__name" href="/itps/'.$itp->id.'" data-transition="forward">The Quiet Yes</a>');
});

it('offers to continue the newest generation still open, under the day’s card', function (string $status, string $text) {
    app(TokenStore::class)->put('1|secret');
    Http::fake(['api.itp.test/v1/generations' => generationsResponse([
        ['id' => '01jc0000000000000000000002', 'status' => 'complete', 'itp_id' => '01jc00000000000000000000aa'],
        ['id' => '01jc0000000000000000000001', 'status' => $status],
    ])]);

    Livewire::actingAs(userLivingWithQuietYes())
        ->test(Home::class)
        ->assertDontSee('Your ITP is being made')
        ->call('refresh')
        ->assertSeeHtmlInOrder(['data-card=', 'Your ITP is being made', $text, 'Continue'])
        ->assertSeeHtml('href="/generations/01jc0000000000000000000001" data-transition="forward"');
})->with([
    'in progress' => ['generating', 'We’re still creating it.'],
    'waiting on answers' => ['awaiting_followup', 'A few questions are waiting for you.'],
    'waiting on the attunement' => ['awaiting_resonance', 'Your attunement is ready to read.'],
]);

it('shows no continue card when nothing is open', function () {
    app(TokenStore::class)->put('1|secret');
    Http::fake(['api.itp.test/v1/generations' => generationsResponse([
        ['id' => '01jc0000000000000000000001', 'status' => 'failed'],
    ])]);

    Livewire::actingAs(User::factory()->create())
        ->test(Home::class)
        ->call('refresh')
        ->assertDontSee('Your ITP is being made');
});

it('quietly leaves out the continue card when the API can\'t be reached', function () {
    app(TokenStore::class)->put('1|secret');
    Http::fake(['api.itp.test/v1/generations' => fn () => throw new ConnectionException('Offline')]);

    Livewire::actingAs(User::factory()->create())
        ->test(Home::class)
        ->call('refresh')
        ->assertHasNoErrors()
        ->assertDontSee('Your ITP is being made')
        ->assertDontSee("We can't reach");
});

it('syncs on the phone when the page has shown', function () {
    useMobileBuild();
    $user = User::factory()->create();
    Http::fake([
        'api.itp.test/v1/generations' => generationsResponse([]),
        'api.itp.test/v1/sync/pull*' => syncPullResponder(['itps' => [[
            'id' => '01jc00000000000000000000aa',
            'user_id' => $user->id,
            'title' => 'Pulled from the server',
            'sync_seq' => 3,
            'deleted_at' => null,
            'created_at' => '2026-11-07 09:10:00',
            'updated_at' => '2026-11-07 09:10:00',
        ]]]),
    ]);

    Livewire::actingAs($user)
        ->test(Home::class)
        ->assertSeeHtml('wire:init="refresh"')
        ->call('refresh');

    expect($user->itps()->pluck('title')->all())->toBe(['Pulled from the server']);
});

it('checks shared content for changes on every load, even when it was pulled moments ago', function () {
    useMobileBuild();
    DB::table('sync_cursors')->insert([
        ['type' => 'universal_itps', 'cursor' => 7, 'pulled_at' => now()],
        ['type' => 'wildcards', 'cursor' => 0, 'pulled_at' => now()],
    ]);
    Http::fake([
        'api.itp.test/v1/generations' => generationsResponse([]),
        'api.itp.test/v1/sync/pull*' => syncPullResponder([]),
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test(Home::class)
        ->call('refresh');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'type=universal_itps'));
});

it('never runs the sync client on the web', function () {
    app(TokenStore::class)->put('1|secret');
    Http::fake(['api.itp.test/v1/generations' => generationsResponse([])]);

    Livewire::actingAs(User::factory()->create())
        ->test(Home::class)
        ->call('refresh');

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/sync/'));
});

it('renders Today as a light room on both builds', function (string $runtime) {
    if ($runtime === 'mobile') {
        useMobileBuild()->items = [];
    }

    $this->get(route('home'))
        ->assertOk()
        ->assertSeeHtml('data-room="light" class="page-center today"')
        ->assertSeeHtml('x-init="itpSyncClock($wire)"');
})->with(['server', 'mobile']);

it('renders the first frame in the device\'s time zone once the browser has said which', function () {
    $this->travelTo('2026-11-16 20:00:00');
    $user = userLivingWithQuietYes();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertSee('data-card="evening-ask"', escape: false);

    $this->actingAs($user)
        ->withUnencryptedCookie('itp_tz', 'Pacific/Auckland')
        ->get(route('home'))
        ->assertSee('data-card="morning-enter"', escape: false)
        ->assertDontSee('Good morning');
});

it('ignores a time zone that isn\'t one', function () {
    $this->travelTo('2026-11-16 20:00:00');

    $this->actingAs(userLivingWithQuietYes())
        ->withUnencryptedCookie('itp_tz', 'Mars/Olympus')
        ->get(route('home'))
        ->assertSee('data-card="evening-ask"', escape: false);
});
