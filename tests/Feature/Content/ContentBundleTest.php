<?php

use App\Content\ContentBundle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Itp\Core\Enums\UniversalItpKind;
use Itp\Core\Models\UniversalItp;
use Itp\Core\Models\Wildcard;

/**
 * The phone applies the bundled universal ITPs and wildcards before any screen reads them
 * (content bundle contract).
 */
beforeEach(function () {
    Http::preventStrayRequests();
    $this->withoutVite();

    useMobileBuild()->items = [];
});

it('writes the bundled rows to the phone on the first request, without moving the sync cursors', function () {
    useContentBundle(contentSnapshot());

    $this->get(route('home'))
        ->assertOk()
        ->assertSeeInOrder(['Introduction', 'Awaken Wonder', 'Purpose']);

    $awaken = UniversalItp::find('01m6skvqm3ddj4z6bqn2dydy0m');
    expect(UniversalItp::count())->toBe(3)
        ->and($awaken->slug)->toBe('awaken-wonder')
        ->and($awaken->kind)->toBe(UniversalItpKind::Universal)
        ->and($awaken->is_preview)->toBeTrue()
        ->and($awaken->breath11_lines)->toBe([['order' => 1, 'text' => 'I breathe in Awaken Wonder']])
        ->and($awaken->attunement_timings[0]['words'][0]['word'])->toBe('Beloved')
        ->and($awaken->updated_at->toDateTimeString())->toBe('2026-11-06 23:45:07')
        ->and($awaken->sync_seq)->toBeNull()
        ->and($awaken->sync_dirty)->toBeFalse()
        ->and(Wildcard::sole()->why)->toBe('Why is everything working out for me?')
        ->and(DB::table('sync_cursors')->count())->toBe(0);
});

it('accepts JSON columns as arrays and ISO times as well as raw column values', function () {
    $snapshot = contentSnapshot();
    $snapshot['types']['universal_itps'][0]['breath11_lines'] = [['order' => 1, 'text' => 'As an array']];
    $snapshot['types']['universal_itps'][0]['updated_at'] = '2026-11-07T08:00:00.000000Z';
    $snapshot['types']['universal_itps'][0]['is_preview'] = true;
    useContentBundle($snapshot);

    app(ContentBundle::class)->applyIfChanged();

    $awaken = UniversalItp::find('01m6skvqm3ddj4z6bqn2dydy0m');
    expect($awaken->breath11_lines)->toBe([['order' => 1, 'text' => 'As an array']])
        ->and($awaken->updated_at->toDateTimeString())->toBe('2026-11-07 08:00:00')
        ->and($awaken->is_preview)->toBeTrue();
});

it('never overwrites a phone row that is as new as or newer than the bundled one', function (string $phoneUpdatedAt, string $expectedTitle) {
    UniversalItp::factory()->create([
        'id' => '01m6skvqm3ddj4z6bqn2dydy0m',
        'slug' => 'awaken-wonder',
        'title' => 'Pulled from the server',
        'updated_at' => $phoneUpdatedAt,
    ]);
    useContentBundle(contentSnapshot());

    app(ContentBundle::class)->applyIfChanged();

    expect(UniversalItp::find('01m6skvqm3ddj4z6bqn2dydy0m')->title)->toBe($expectedTitle);
})->with([
    'phone row older' => ['2026-11-01 10:00:00', 'Light Up Your Life'],
    'phone row the same age' => ['2026-11-06 23:45:07', 'Pulled from the server'],
    'phone row newer' => ['2026-11-07 10:00:00', 'Pulled from the server'],
]);

it('applies the bundle again only when its contents change', function () {
    $path = useContentBundle(contentSnapshot());
    $bundle = app(ContentBundle::class);

    expect($bundle->applyIfChanged())->toBeTrue();
    UniversalItp::find('01m6skvqmbn7njtwxbfqwv8syn')->forceDelete();

    expect($bundle->applyIfChanged())->toBeFalse();

    touch($path, time() + 60);
    expect($bundle->applyIfChanged())->toBeFalse()
        ->and(UniversalItp::find('01m6skvqmbn7njtwxbfqwv8syn'))->toBeNull();

    $snapshot = contentSnapshot();
    $snapshot['types']['universal_itps'][0]['title'] = 'Light Up Your Whole Life';
    $snapshot['types']['universal_itps'][0]['updated_at'] = '2026-11-08 09:00:00';
    useContentBundle($snapshot, $path);

    expect($bundle->applyIfChanged())->toBeTrue()
        ->and(UniversalItp::find('01m6skvqm3ddj4z6bqn2dydy0m')->title)->toBe('Light Up Your Whole Life')
        ->and(UniversalItp::find('01m6skvqmbn7njtwxbfqwv8syn'))->not->toBeNull();
});

it('never applies a bundle on the web build', function () {
    config(['itp.runtime' => 'server', 'sync.role' => 'server']);
    useContentBundle(contentSnapshot());

    $this->get(route('home'))->assertOk();

    expect(UniversalItp::count())->toBe(0);
});

it('still shows the page when the bundle is broken', function () {
    $path = tempnam(sys_get_temp_dir(), 'itp-content-');
    file_put_contents($path, '{"types": ');
    config(['itp.content_bundle' => $path]);

    $this->get(route('home'))->assertOk();

    expect(UniversalItp::count())->toBe(0);
});
