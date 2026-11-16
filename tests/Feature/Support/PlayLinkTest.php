<?php

use App\Support\PlayLink;
use Itp\Core\Models\Itp;
use Itp\Core\Models\UniversalItp;

it('links to the player by path: a universal ITP by slug, a personal one by id', function () {
    $universal = UniversalItp::factory()->create(['slug' => 'awaken-wonder']);
    $personal = Itp::factory()->create();

    expect(PlayLink::for($universal, 'itp'))->toBe('/play/universal/awaken-wonder/itp')
        ->and(PlayLink::for($universal, 'breathe', 'today'))->toBe('/play/universal/awaken-wonder/breathe?from=today')
        ->and(PlayLink::for($personal, 'why'))->toBe("/play/itp/{$personal->id}/why");
});

it('rejects a piece or a return the player doesn\'t know', function (string $piece, ?string $from) {
    PlayLink::for(UniversalItp::factory()->create(), $piece, $from);
})->with([
    'an unknown piece' => ['luminous', null],
    'an unknown return' => ['itp', 'library'],
])->throws(InvalidArgumentException::class);
