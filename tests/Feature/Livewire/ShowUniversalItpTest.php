<?php

use App\Livewire\ShowUniversalItp;
use App\Media\MediaDownload;
use App\Media\MediaDownloadStatus;
use Illuminate\Support\Facades\Storage;
use Itp\Core\Models\UniversalItp;
use Itp\Core\Models\User;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('media');
    config(['itp.media_url' => 'https://media.itp.test']);
});

it('shows a guest a preview, with its bundled audio', function () {
    $itp = UniversalItp::factory()->preview()->create([
        'short_name' => 'Awaken Wonder',
        'description' => 'Reconnect with magic.',
        'image_path' => 'media/images/universals/original/1-awaken-wonder.webp',
        'attunement_text' => "Beloved Dreamer\nHave you stopped believing?\n\nRemember Dear One",
        'attunement_audio_path' => 'media/audio/universals/activations/1-awaken-wonder.mp3',
        'breath11_lines' => [['order' => 2, 'text' => 'I breathe out the doubt.'], ['order' => 1, 'text' => 'I breathe in wonder.']],
        'breath11_audio_path' => 'media/audio/universals/breath11/1-awaken-wonder.mp3',
        'quantum_whys' => ['Why is life so magical?'],
    ]);

    $this->get(route('universals.show', $itp))
        ->assertOk()
        ->assertSeeLivewire(ShowUniversalItp::class)
        ->assertSee('<title>Awaken Wonder · ', escape: false)
        ->assertSeeInOrder([
            'src="'.asset('media/images/universals/original/1-awaken-wonder.webp').'"',
            'Awaken Wonder',
            'Reconnect with magic.',
            'src="'.asset('media/audio/universals/activations/1-awaken-wonder.mp3').'"',
            'src="'.asset('media/audio/universals/breath11/1-awaken-wonder.mp3').'"',
            "<p class=\"whitespace-pre-line\">Beloved Dreamer\nHave you stopped believing?</p>",
            '<p class="whitespace-pre-line">Remember Dear One</p>',
            'I breathe in wonder.',
            'I breathe out the doubt.',
            'Why is life so magical?',
        ], escape: false);
});

it('sends a guest to sign in for any ITP but a preview', function () {
    $itp = UniversalItp::factory()->create();

    $this->get(route('universals.show', $itp))->assertRedirect(route('sign-in'));
});

it('opens every ITP once signed in, streaming audio the app doesn\'t bundle on the web', function () {
    $itp = UniversalItp::factory()->create(['attunement_audio_path' => 'media/audio/universals/activations/4-purpose.mp3']);

    $this->actingAs(User::factory()->create())
        ->get(route('universals.show', $itp))
        ->assertOk()
        ->assertSee('<audio controls preload="none" src="https://media.itp.test/media/audio/universals/activations/4-purpose.mp3"', escape: false);
});

it('shows where each audio stands on the phone', function (?MediaDownloadStatus $download, bool $downloaded, string $expected) {
    $keychain = useMobileBuild();
    $user = User::factory()->create();
    $keychain->items['itp.user_id'] = $user->id;
    $key = 'media/audio/universals/activations/4-purpose.mp3';
    $itp = UniversalItp::factory()->create(['attunement_audio_path' => $key]);

    if ($download !== null) {
        MediaDownload::create(['key' => $key, 'status' => $download]);
    }

    if ($downloaded) {
        Storage::disk('media')->put($key, 'mp3 bytes');
    }

    $this->get(route('universals.show', $itp))
        ->assertOk()
        ->assertSee($expected, escape: false);
})->with([
    'downloaded' => [MediaDownloadStatus::Downloaded, true, 'src="/media/media/audio/universals/activations/4-purpose.mp3"'],
    'not downloaded yet' => [null, false, 'Downloading…'],
    'download failed' => [MediaDownloadStatus::Failed, false, 'Downloads when you’re online.'],
]);

it('asks a guest on the phone to sign in for preview audio the app doesn\'t have', function () {
    useMobileBuild()->items = [];
    $itp = UniversalItp::factory()->preview()->create(['attunement_audio_path' => 'media/audio/universals/activations/1-awaken-wonder.v2.mp3']);

    $this->get(route('universals.show', $itp))
        ->assertOk()
        ->assertSee('href="/sign-in" data-transition="forward"', escape: false)
        ->assertSee('Sign in to play')
        ->assertDontSee('<audio', escape: false);
});

it('sends a signed-in user without a first name to the profile screen first', function () {
    $itp = UniversalItp::factory()->preview()->create();

    $this->actingAs(User::factory()->withoutFirstName()->create())
        ->get(route('universals.show', $itp))
        ->assertRedirect(route('profile'));
});

it('is not found for an unknown slug', function () {
    $this->get('/universal/no-such-itp')->assertNotFound();
});
