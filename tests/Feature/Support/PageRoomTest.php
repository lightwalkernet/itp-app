<?php

use App\Support\PageRoom;
use Itp\Core\Models\UniversalItp;

beforeEach(function () {
    $this->withoutVite();
});

it('reads the room a page declares, as rooms.js does', function (string $page, array $attributes) {
    expect(PageRoom::attributes($page))->toBe($attributes);
})->with([
    'a white room' => ['<div wire:id="x" data-room="white"><p>Your ITPs</p></div>', ['data-room' => 'white']],
    'a light room' => ['<div data-room="light" class="page-center">…</div>', ['data-room' => 'light']],
    'a place you are in' => ['<div data-room="light" data-bar="hidden" data-scene="inside">…</div>', ['data-room' => 'light', 'data-bar' => 'hidden', 'data-scene' => 'inside']],
    'no room declared' => ['<div><p>hello</p></div>', ['data-room' => 'light']],
    'only the first room counts' => ['<div data-room="white"><div data-room="light" data-bar="hidden"></div></div>', ['data-room' => 'white']],
    'an unknown room is light' => ['<div data-room="garden"></div>', ['data-room' => 'light']],
]);

it('puts the page\'s room on <html> in the response itself, so the first frame is right', function () {
    expect($this->get(route('itps.index'))->getContent())->toMatch('/<html[^>]*\sdata-room="white"/')
        ->and($this->get(route('home'))->getContent())->toMatch('/<html[^>]*\sdata-room="light"/');
});

it('sets the time of day in the head, before the first frame', function () {
    UniversalItp::factory()->create();

    $html = $this->get(route('home'))->getContent();

    expect(strpos($html, 'root.dataset.phase ='))->toBeLessThan(strpos($html, '<body'))
        ->and($html)->toContain("localStorage.getItem('itp.still')");
});
