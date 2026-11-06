<?php

use App\Media\MediaLibrary;
use Illuminate\Support\Facades\Storage;

const BUNDLED_AUDIO = 'media/audio/universals/activations/1-awaken-wonder.mp3';

const REMOTE_AUDIO = 'media/audio/universals/activations/4-purpose.mp3';

beforeEach(function () {
    Storage::fake('media');
    config(['itp.media_url' => 'https://media.itp.test/']);
});

it('ships the preview and Introduction audio and every universal image in public/', function () {
    $keys = [
        'media/audio/base/introduction.mp3',
        ...array_map(fn (string $name) => "media/audio/universals/activations/{$name}.mp3", ['1-awaken-wonder', '2-abundance', '3-healing', '11-nervous-system']),
        ...array_map(fn (string $name) => "media/audio/universals/breath11/{$name}.mp3", ['1-awaken-wonder', '2-abundance', '3-healing', '11-nervous-system']),
    ];

    foreach ($keys as $key) {
        expect(filesize(public_path($key)))->toBeGreaterThan(200_000);
    }

    expect(glob(public_path('media/images/universals/original/*.webp')))->toHaveCount(13);
});

it('plays a bundled file from public/ on both builds', function (string $runtime) {
    config(['itp.runtime' => $runtime]);

    expect(app(MediaLibrary::class)->url(BUNDLED_AUDIO))->toBe(asset(BUNDLED_AUDIO));
})->with(['server', 'mobile']);

it('streams anything else from the media host on the web', function () {
    expect(app(MediaLibrary::class)->url(REMOTE_AUDIO))->toBe('https://media.itp.test/'.REMOTE_AUDIO);
});

it('plays a downloaded file on the phone by path, and has nothing before it is downloaded', function () {
    config(['itp.runtime' => 'mobile']);
    $media = app(MediaLibrary::class);

    expect($media->url(REMOTE_AUDIO))->toBeNull();

    Storage::disk('media')->put(REMOTE_AUDIO, 'mp3');

    expect($media->url(REMOTE_AUDIO))->toBe('/media/'.REMOTE_AUDIO);
});

it('resolves no URL for a missing or unsafe key', function (?string $key) {
    expect(app(MediaLibrary::class)->url($key))->toBeNull();
})->with([null, '', '../.env', 'media/../../.env', '/etc/passwd', 'media/.partial/abc', 'media\\audio.mp3']);

it('serves a downloaded file on the phone', function () {
    config(['itp.runtime' => 'mobile']);
    Storage::disk('media')->put(REMOTE_AUDIO, 'mp3 bytes');

    $response = $this->get('/media/'.REMOTE_AUDIO)->assertOk();

    expect($response->baseResponse->getFile()->getPathname())->toBe(Storage::disk('media')->path(REMOTE_AUDIO));
});

it('serves nothing outside the downloads, or on the web build', function (string $runtime, string $key) {
    config(['itp.runtime' => $runtime]);
    Storage::disk('media')->put(REMOTE_AUDIO, 'mp3 bytes');
    Storage::disk('media')->put('.partial/abc', 'half a file');

    $this->get('/media/'.$key)->assertNotFound();
})->with([
    'web build' => ['server', REMOTE_AUDIO],
    'not downloaded' => ['mobile', 'media/audio/universals/activations/5-break-bad-habits.mp3'],
    'unfinished download' => ['mobile', '.partial/abc'],
    'outside the folder' => ['mobile', 'media/../../../.env'],
]);
