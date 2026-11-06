<?php

use App\Livewire\Home;
use App\Media\MediaDownload;
use App\Media\MediaDownloadStatus;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Itp\Core\Models\UniversalItp;
use Itp\Core\Models\User;
use Livewire\Livewire;

/**
 * Signed in on the phone, Home downloads the universal audio the app doesn't bundle, one file
 * per poll (platform.md §7, content bundle contract).
 */
beforeEach(function () {
    Http::preventStrayRequests();
    Storage::fake('media');
    config(['itp.media_url' => 'https://media.itp.test']);

    $this->keychain = useMobileBuild();
    $this->user = User::factory()->create();
    $this->keychain->items['itp.user_id'] = $this->user->id;
});

/**
 * A universal ITP with the given audio keys.
 */
function universalWithAudio(string $attunement, ?string $breath11 = null): UniversalItp
{
    return UniversalItp::factory()->create(['attunement_audio_path' => $attunement, 'breath11_audio_path' => $breath11]);
}

it('downloads each missing audio file, one per call, and skips bundled ones', function () {
    universalWithAudio('media/audio/universals/activations/1-awaken-wonder.mp3', 'media/audio/universals/breath11/1-awaken-wonder.mp3');
    universalWithAudio('media/audio/universals/activations/4-purpose.mp3', 'media/audio/universals/breath11/4-purpose.mp3');
    Http::fake(['media.itp.test/*' => fn () => Http::response('mp3 bytes')]);

    $home = Livewire::actingAs($this->user)->test(Home::class)
        ->assertSeeHtml('wire:poll.3s="downloadNext"')
        ->call('downloadNext');

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->url() === 'https://media.itp.test/media/audio/universals/activations/4-purpose.mp3');
    Storage::disk('media')->assertExists('media/audio/universals/activations/4-purpose.mp3');
    $home->assertSeeHtml('wire:poll.3s="downloadNext"');

    $home->call('downloadNext')->assertDontSeeHtml('wire:poll');

    Http::assertSentCount(2);
    expect(Storage::disk('media')->get('media/audio/universals/breath11/4-purpose.mp3'))->toBe('mp3 bytes')
        ->and(MediaDownload::query()->pluck('status', 'key')->all())->toBe([
            'media/audio/universals/activations/4-purpose.mp3' => MediaDownloadStatus::Downloaded,
            'media/audio/universals/breath11/4-purpose.mp3' => MediaDownloadStatus::Downloaded,
        ])
        ->and(MediaDownload::query()->value('bytes'))->toBe(9)
        ->and(Storage::disk('media')->allFiles())->toHaveCount(2);

    $home->call('downloadNext');
    Http::assertSentCount(2);
});

it('downloads a new key when the audio path changes, and deletes the file nothing references', function () {
    $itp = universalWithAudio('media/audio/universals/activations/4-purpose.mp3');
    Storage::disk('media')->put('media/audio/universals/activations/4-purpose.mp3', 'old recording');
    MediaDownload::create(['key' => 'media/audio/universals/activations/4-purpose.mp3', 'status' => MediaDownloadStatus::Downloaded, 'bytes' => 13]);
    $itp->update(['attunement_audio_path' => 'media/audio/universals/activations/4-purpose.v2.mp3']);
    Http::fake(['media.itp.test/*' => Http::response('new recording')]);

    Livewire::actingAs($this->user)->test(Home::class)->call('downloadNext');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '4-purpose.v2.mp3'));
    expect(Storage::disk('media')->allFiles())->toBe(['media/audio/universals/activations/4-purpose.v2.mp3'])
        ->and(MediaDownload::query()->pluck('key')->all())->toBe(['media/audio/universals/activations/4-purpose.v2.mp3']);
});

it('marks a failed download, keeps no file, and tries again on the next Home load', function (Closure $response) {
    universalWithAudio('media/audio/universals/activations/4-purpose.mp3');
    Http::fake([
        'media.itp.test/*' => $response,
        'api.itp.test/v1/sync/pull*' => syncPullResponder([]),
        'api.itp.test/v1/generations' => Http::response(['message' => 'OK', 'errors' => (object) [], 'data' => ['generations' => []]]),
    ]);

    $home = Livewire::actingAs($this->user)->test(Home::class)
        ->call('downloadNext')
        ->assertDontSeeHtml('wire:poll');

    expect(MediaDownload::sole()->status)->toBe(MediaDownloadStatus::Failed)
        ->and(Storage::disk('media')->allFiles())->toBe([]);

    $home->call('refresh')->assertSeeHtml('wire:poll.3s="downloadNext"');
})->with([
    'not found' => [fn () => Http::response('Not found', 404)],
    'empty' => [fn () => Http::response('')],
]);

it('downloads nothing for a guest', function () {
    $this->keychain->items = [];
    universalWithAudio('media/audio/universals/activations/4-purpose.mp3');

    Livewire::test(Home::class)
        ->assertDontSeeHtml('wire:poll')
        ->call('downloadNext');

    Http::assertNothingSent();
});
