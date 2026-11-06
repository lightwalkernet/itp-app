<?php

use App\Auth\TokenStore;
use App\Livewire\Home;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Itp\Core\Models\Itp;
use Itp\Core\Models\UniversalItp;
use Itp\Core\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    Http::preventStrayRequests();
    $this->withoutVite();
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

it('lists the user\'s own ITPs, newest first', function () {
    $user = User::factory()->create();
    Itp::factory()->for($user)->create(['title' => 'Older ITP', 'description' => 'The first one.', 'created_at' => now()->subDay()]);
    Itp::factory()->for($user)->create(['title' => 'Newer ITP', 'created_at' => now()]);
    Itp::factory()->create(['title' => 'Someone else\'s ITP']);

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertSeeInOrder(['Create a personal ITP', 'Newer ITP', 'Older ITP', 'The first one.'])
        ->assertDontSee('Someone else\'s ITP');
});

it('offers to continue the newest generation still open', function (string $status, string $text) {
    app(TokenStore::class)->put('1|secret');
    Http::fake(['api.itp.test/v1/generations' => generationsResponse([
        ['id' => '01jc0000000000000000000002', 'status' => 'complete', 'itp_id' => '01jc00000000000000000000aa'],
        ['id' => '01jc0000000000000000000001', 'status' => $status],
    ])]);

    Livewire::actingAs(User::factory()->create())
        ->test(Home::class)
        ->assertDontSee('Continue your ITP')
        ->call('refresh')
        ->assertSee('Continue your ITP')
        ->assertSee($text)
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
        ->assertDontSee('Continue your ITP');
});

it('quietly leaves out the continue card when the API can\'t be reached', function () {
    app(TokenStore::class)->put('1|secret');
    Http::fake(['api.itp.test/v1/generations' => fn () => throw new ConnectionException('Offline')]);

    Livewire::actingAs(User::factory()->create())
        ->test(Home::class)
        ->call('refresh')
        ->assertHasNoErrors()
        ->assertDontSee('Continue your ITP')
        ->assertDontSee("We can't reach");
});

it('syncs on the phone when refreshed, and shows the pulled ITPs', function () {
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
        ->call('refresh')
        ->assertSee('Pulled from the server');
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

it('shows guests the universal ITPs on both builds: previews open, the others locked behind sign-in', function (string $runtime) {
    if ($runtime === 'mobile') {
        useMobileBuild()->items = [];
    }
    $preview = UniversalItp::factory()->preview()->create(['short_name' => 'Awaken Wonder', 'description' => 'Reconnect with magic.', 'sort_order' => 2, 'image_path' => 'media/images/universals/original/1-awaken-wonder.webp']);
    $locked = UniversalItp::factory()->create(['short_name' => 'Purpose', 'description' => 'Find your why.', 'sort_order' => 1]);
    UniversalItp::factory()->base()->create(['short_name' => 'Introduction', 'sort_order' => null]);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('Create a personal ITP')
        ->assertSeeInOrder(['Introduction', 'Purpose', 'Find your why.', 'Sign in to play', 'Awaken Wonder', 'Reconnect with magic.'])
        ->assertSee('href="/sign-in" data-transition="forward"', escape: false)
        ->assertSee('href="/universal/'.$preview->slug.'" data-transition="forward"', escape: false)
        ->assertDontSee('href="/universal/'.$locked->slug.'"', escape: false)
        ->assertSee('src="'.asset('media/images/universals/original/1-awaken-wonder.webp').'"', escape: false);
})->with(['server', 'mobile']);

it('unlocks every universal ITP once signed in', function () {
    $locked = UniversalItp::factory()->create(['short_name' => 'Purpose']);

    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertOk()
        ->assertSee('Create a personal ITP')
        ->assertSee('Purpose')
        ->assertSee('href="/universal/'.$locked->slug.'" data-transition="forward"', escape: false)
        ->assertDontSee('Sign in to play');
});
