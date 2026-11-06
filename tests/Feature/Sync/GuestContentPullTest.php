<?php

use App\Livewire\Home;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Itp\Core\Models\UniversalItp;
use Itp\Core\Models\User;
use Itp\Core\Models\Wildcard;
use Livewire\Livewire;

/**
 * A signed-out phone pulls universal ITPs and wildcards from itp-server's public content pull on
 * every Home load, with the sync client's own semantics (content bundle contract).
 */
beforeEach(function () {
    Http::preventStrayRequests();
    $this->withoutVite();
    config(['sync.client.retries' => 0]);

    $this->keychain = useMobileBuild();
    $this->keychain->items = [];
});

/**
 * A universal ITP row as the pull sends it: raw column values.
 *
 * @param  array<string, mixed>  $attributes
 * @return array<string, mixed>
 */
function pulledUniversal(array $attributes): array
{
    return $attributes + [
        'slug' => 'pulled-'.$attributes['id'],
        'kind' => 'universal',
        'sort_order' => 1,
        'title' => 'Pulled title',
        'short_name' => null,
        'is_preview' => 1,
        'quantum_whys' => '["Why is this so easy?"]',
        'deleted_at' => null,
        'created_at' => '2026-11-07 09:00:00',
        'updated_at' => '2026-11-07 09:00:00',
    ];
}

it('pulls content without a token, applies it and moves the cursor on', function () {
    $retired = UniversalItp::factory()->preview()->create(['short_name' => 'Retired ITP']);
    Http::fake(['api.itp.test/v1/content/pull*' => syncPullResponder([
        'universal_itps' => [
            pulledUniversal(['id' => '01jc00000000000000000000u1', 'short_name' => 'Fresh from Studio', 'sync_seq' => 4]),
            pulledUniversal(['id' => $retired->id, 'slug' => $retired->slug, 'deleted_at' => '2026-11-07 09:30:00', 'sync_seq' => 7]),
        ],
        'wildcards' => [
            ['id' => '01jc00000000000000000000w1', 'why' => 'Why does it keep getting better?', 'sync_seq' => 9, 'deleted_at' => null, 'created_at' => '2026-11-07 09:00:00', 'updated_at' => '2026-11-07 09:00:00'],
        ],
    ])]);

    $home = Livewire::test(Home::class)
        ->assertSee('Retired ITP')
        ->call('refresh')
        ->assertSee('Fresh from Studio')
        ->assertDontSee('Retired ITP');

    expect(UniversalItp::find('01jc00000000000000000000u1')->quantum_whys)->toBe(['Why is this so easy?'])
        ->and(Wildcard::sole()->why)->toBe('Why does it keep getting better?')
        ->and(DB::table('sync_cursors')->pluck('cursor', 'type')->all())->toBe(['universal_itps' => 7, 'wildcards' => 9]);
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'content/pull?type=universal_itps&after=0')
        && ! $request->hasHeader('Authorization')
        && $request->header('X-App-Version') === ['1.4.2']);

    $home->call('refresh');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'type=universal_itps&after=7'));
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'sync/') || str_contains($request->url(), 'type=users') || str_contains($request->url(), 'type=itps'));
});

it('keeps showing what the phone has when the content pull fails', function () {
    UniversalItp::factory()->preview()->create(['short_name' => 'Already here']);
    Http::fake(['api.itp.test/v1/content/pull*' => fn () => throw new ConnectionException('Offline')]);

    Livewire::test(Home::class)
        ->call('refresh')
        ->assertHasNoErrors()
        ->assertSee('Already here');

    expect(DB::table('sync_cursors')->count())->toBe(0);
});

it('leaves content to the signed-in sync once signed in', function () {
    $user = User::factory()->create();
    $this->keychain->items = ['itp.api_token' => '1|secret', 'itp.user_id' => $user->id];
    Http::fake([
        'api.itp.test/v1/generations' => Http::response(['message' => 'OK', 'errors' => (object) [], 'data' => ['generations' => []]]),
        'api.itp.test/v1/sync/pull*' => syncPullResponder([]),
    ]);

    Livewire::actingAs($user)->test(Home::class)->call('refresh');

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'content/pull'));
});

it('never pulls content on the web build', function () {
    config(['itp.runtime' => 'server', 'sync.role' => 'server']);

    Livewire::test(Home::class)->call('refresh');

    Http::assertNothingSent();
});
