<?php

use App\Livewire\Player;
use App\Media\MediaDownload;
use App\Media\MediaDownloadStatus;
use App\Support\Welcome;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Storage;
use Itp\Core\Models\Itp;
use Itp\Core\Models\UniversalItp;
use Itp\Core\Models\User;
use Livewire\Livewire;

/**
 * The player (player contract): who may play what, what the page gives player.js to run, and
 * where finishing goes. Playback itself runs in the browser.
 */
beforeEach(function () {
    $this->withoutVite();
    Storage::fake('media');
    config(['itp.media_url' => 'https://media.itp.test']);
});

/**
 * Two timed sentences, as itp-server exports them.
 *
 * @return list<array<string, mixed>>
 */
function playerTimings(): array
{
    return [
        ['start' => 0.8, 'end' => 3.36, 'text' => 'The world’s spell is breaking', 'words' => [
            ['word' => 'The', 'start' => 0.8, 'end' => 1.2],
            ['word' => 'world’s', 'start' => 1.2, 'end' => 1.76],
            ['word' => 'spell', 'start' => 1.76, 'end' => 2.16],
            ['word' => 'is', 'start' => 2.16, 'end' => 2.48],
            ['word' => 'breaking', 'start' => 2.48, 'end' => 3.36],
        ]],
        ['start' => 3.92, 'end' => 5.04, 'text' => 'And you are awakening', 'words' => [
            ['word' => 'And', 'start' => 3.92, 'end' => 4.08],
            ['word' => 'you', 'start' => 4.08, 'end' => 4.24],
            ['word' => 'are', 'start' => 4.24, 'end' => 4.48],
            ['word' => 'awakening', 'start' => 4.48, 'end' => 5.04],
        ]],
    ];
}

/**
 * @param  array<string, mixed>  $attributes
 */
function playableUniversal(array $attributes = []): UniversalItp
{
    return UniversalItp::factory()->preview()->create([
        'slug' => 'awaken-wonder',
        'short_name' => 'Awaken Wonder',
        'attunement_timings' => playerTimings(),
        'attunement_audio_path' => 'media/audio/universals/activations/1-awaken-wonder.mp3',
        'breath11_timings' => playerTimings(),
        'breath11_audio_path' => 'media/audio/universals/breath11/1-awaken-wonder.mp3',
        'quantum_whys' => ['Why is life so magical?', 'Why does wonder find me so easily?'],
        ...$attributes,
    ]);
}

function playerIntroduction(): UniversalItp
{
    return UniversalItp::factory()->base()->create([
        'slug' => 'introduction',
        'short_name' => 'Introduction',
        'is_preview' => false,
        'attunement_timings' => playerTimings(),
        'attunement_audio_path' => 'media/audio/base/introduction.mp3',
        'breath11_audio_path' => null,
        'quantum_whys' => null,
    ]);
}

it('plays an attunement\'s sentences and words, timed, with its voice, in a light room without the bar', function () {
    playableUniversal();

    $this->get('/play/universal/awaken-wonder/itp')
        ->assertOk()
        ->assertSeeLivewire(Player::class)
        ->assertSee('<title>Awaken Wonder · ', escape: false)
        ->assertSee('data-room="light" data-bar="hidden" class="player"', escape: false)
        ->assertSeeInOrder([
            'x-data="itpWordsPlayer({ marksTheDay: false })"',
            'aria-label="Play"',
            '<div class="player-sentence" data-sentence="0" data-start="0.8" data-end="3.36" data-state="hidden">',
            '<span>The</span>',
            '<span>breaking</span>',
            '<span class="player-word" data-word="0-0" data-start="0.8"><span class="player-word__ghost">The</span></span>',
            '<span class="player-word" data-word="0-4" data-start="2.48"><span class="player-word__ghost">breaking</span></span>',
            '<div class="player-sentence" data-sentence="1" data-start="3.92" data-end="5.04" data-state="hidden">',
            '<span class="player-word" data-word="1-3" data-start="4.48"><span class="player-word__ghost">awakening</span></span>',
            'aria-label="Stop"',
            'aria-label="Restart"',
            '<audio x-ref="voice" preload="auto" src="'.asset('media/audio/universals/activations/1-awaken-wonder.mp3').'">',
        ], escape: false)
        ->assertDontSee('skip intro?');
});

it('reads timings left as JSON text, and plays a sentence without word times as one word', function () {
    playableUniversal(['attunement_timings' => json_encode([['start' => 1, 'end' => 2.5, 'text' => 'Beloved One', 'words' => []]])]);

    $this->get('/play/universal/awaken-wonder/itp')
        ->assertOk()
        ->assertSee('<span class="player-word" data-word="0-0" data-start="1"><span class="player-word__ghost">Beloved One</span></span>', escape: false);
});

it('plays Breathe 11\'s lines with its voice and the pacer\'s bundled sounds', function () {
    playableUniversal();

    $this->get('/play/universal/awaken-wonder/breathe')
        ->assertOk()
        ->assertSee('<title>Breathe 11 · ', escape: false)
        ->assertSeeInOrder([
            'x-data="itpBreathePlayer()"',
            'class="player-words player-words--breathe"',
            'data-sentence="0" data-start="0.8" data-end="3.36"',
            'data-word="1-3" data-start="4.48"',
            'data-itis',
            'And so it is',
            'class="breath__label"',
            '<audio x-ref="voice" preload="auto" src="'.asset('media/audio/universals/breath11/1-awaken-wonder.mp3').'">',
            'data-sound="breathin" preload="auto" src="'.asset('media/audio/base/breathin.mp3').'"',
            'data-sound="breathout" preload="auto" src="'.asset('media/audio/base/breathout.mp3').'"',
            'data-sound="in" preload="auto" src="'.asset('media/audio/base/in.mp3').'"',
            'data-sound="out" preload="auto" src="'.asset('media/audio/base/out.mp3').'"',
            'data-sound="soitis" preload="auto" src="'.asset('media/audio/base/soitis.mp3').'"',
        ], escape: false);

    foreach (Player::BREATH_SOUNDS as $sound) {
        expect(public_path("media/audio/base/{$sound}.mp3"))->toBeFile();
    }
});

it('gives the browser every Quantum Why to pick from, with no audio, even for a personal ITP', function () {
    $user = User::factory()->create();
    $itp = Itp::factory()->forUser($user)->create([
        'attunement_audio_path' => null,
        'quantum_whys' => ['Why is it so easy for me to rest?', 'Why do I trust myself more each day?'],
    ]);

    $this->actingAs($user)
        ->get("/play/itp/{$itp->id}/why")
        ->assertOk()
        ->assertSee('<title>A Quantum Why · ', escape: false)
        ->assertSee('data-whys-key="itp:'.$itp->id.'"', escape: false)
        ->assertSeeInOrder(['Why is it so easy for me to rest?', 'Why do I trust myself more each day?', 'aria-label="Back"'], escape: false)
        ->assertDontSee('<audio', escape: false);
});

it('lets a guest play a preview and the Introduction, and asks them to sign in for the rest', function () {
    playableUniversal();
    playerIntroduction();
    UniversalItp::factory()->create(['slug' => 'purpose', 'is_preview' => false]);

    $this->get('/play/universal/awaken-wonder/breathe')->assertOk();
    $this->get('/play/universal/introduction/itp')->assertOk();
    $this->get('/play/universal/purpose/itp')->assertRedirect(route('sign-in'));
    $this->get('/play/universal/purpose/why')->assertRedirect(route('sign-in'));
});

it('plays every universal ITP once signed in, streaming audio the web build doesn\'t bundle', function () {
    UniversalItp::factory()->create(['slug' => 'purpose', 'is_preview' => false, 'attunement_audio_path' => 'media/audio/universals/activations/4-purpose.mp3']);

    $this->actingAs(User::factory()->create())
        ->get('/play/universal/purpose/itp')
        ->assertOk()
        ->assertSee('src="https://media.itp.test/media/audio/universals/activations/4-purpose.mp3"', escape: false);
});

it('plays a personal ITP for its owner only', function () {
    $owner = User::factory()->create();
    $itp = Itp::factory()->forUser($owner)->create(['attunement_audio_path' => 'itps/one/attunement.m4a']);

    $this->actingAs($owner)->get("/play/itp/{$itp->id}/itp")->assertOk()->assertSee('data-sentence="0"', escape: false);
    $this->actingAs(User::factory()->create())->get("/play/itp/{$itp->id}/itp")->assertNotFound();
    auth()->logout();
    $this->get("/play/itp/{$itp->id}/itp")->assertNotFound();
});

it('is not found for an unknown piece, source or ITP', function (string $path) {
    playableUniversal();

    $this->get($path)->assertNotFound();
})->with([
    'an unknown piece' => ['/play/universal/awaken-wonder/luminous'],
    'an unknown source' => ['/play/wildcard/awaken-wonder/itp'],
    'an unknown slug' => ['/play/universal/no-such-itp/itp'],
    'an unknown personal ITP' => ['/play/itp/01jc0000000000000000000000/itp'],
]);

it('says so calmly, and plays nothing, when a piece has no voice yet', function (string $piece, array $attributes) {
    playableUniversal($attributes);

    $this->get("/play/universal/awaken-wonder/{$piece}")
        ->assertOk()
        ->assertSee('This one doesn’t have its voice yet.')
        ->assertSee('href="/universal/awaken-wonder" data-transition="back"', escape: false)
        ->assertDontSee('<audio', escape: false)
        ->assertDontSee('data-sentence', escape: false);
})->with([
    'an attunement without audio' => ['itp', ['attunement_audio_path' => null]],
    'an attunement without timings' => ['itp', ['attunement_timings' => null]],
    'a Breathe 11 without audio' => ['breathe', ['breath11_audio_path' => null]],
]);

it('says so when there are no Quantum Whys', function () {
    playableUniversal(['quantum_whys' => []]);

    $this->get('/play/universal/awaken-wonder/why')
        ->assertOk()
        ->assertSee('This one doesn’t have its Quantum Whys yet.')
        ->assertDontSee('data-whys-key', escape: false);
});

it('waits for a download on the phone, and plays the downloaded file once it is there', function (bool $downloaded, ?MediaDownloadStatus $status) {
    $keychain = useMobileBuild();
    $user = User::factory()->create();
    $keychain->items['itp.user_id'] = $user->id;
    $key = 'media/audio/universals/activations/4-purpose.mp3';
    UniversalItp::factory()->create(['slug' => 'purpose', 'attunement_audio_path' => $key]);

    if ($status !== null) {
        MediaDownload::create(['key' => $key, 'status' => $status]);
    }

    if ($downloaded) {
        Storage::disk('media')->put($key, 'mp3 bytes');
    }

    $response = $this->get('/play/universal/purpose/itp')->assertOk();

    $downloaded
        ? $response->assertSee('src="/media/media/audio/universals/activations/4-purpose.mp3"', escape: false)->assertDontSee('Still downloading')
        : $response->assertSee('Still downloading — try again in a moment.')->assertDontSee('<audio', escape: false);
})->with([
    'not downloaded yet' => [false, null],
    'the download failed' => [false, MediaDownloadStatus::Failed],
    'downloaded' => [true, MediaDownloadStatus::Downloaded],
]);

it('asks a guest on the phone to sign in for a preview whose voice the app doesn\'t bundle', function () {
    useMobileBuild()->items = [];
    playableUniversal(['attunement_audio_path' => 'media/audio/universals/activations/1-awaken-wonder.v2.mp3']);

    $this->get('/play/universal/awaken-wonder/itp')->assertRedirect(route('sign-in'));
});

it('marks the day entered, on the device\'s app day, when Today\'s attunement starts playing', function () {
    $this->travelTo('2026-11-16 12:00:00');
    playableUniversal();

    Livewire::withQueryParams(['from' => 'today'])
        ->test(Player::class, ['source' => 'universal', 'key' => 'awaken-wonder', 'piece' => 'itp'])
        ->assertSee('x-init="itpSyncClock($wire)"', escape: false)
        ->call('setClock', 23, '2026-11-15', 30)
        ->call('markEntered');

    expect(session('entered:2026-11-15'))->toBeTrue()
        ->and(session()->has('entered:2026-11-16'))->toBeFalse();
});

it('leaves the day alone when the attunement isn\'t entered from Today', function (array $query, string $piece) {
    $this->travelTo('2026-11-16 12:00:00');
    playableUniversal();

    Livewire::withQueryParams($query)
        ->test(Player::class, ['source' => 'universal', 'key' => 'awaken-wonder', 'piece' => $piece])
        ->call('markEntered');

    expect(session()->has('entered:2026-11-16'))->toBeFalse();
})->with([
    'from an ITP\'s page' => [[], 'itp'],
    'Today\'s Breathe 11' => [['from' => 'today'], 'breathe'],
]);

it('goes back where the person came from when it finishes', function (array $query, string $url) {
    playableUniversal();

    Livewire::withQueryParams($query)
        ->test(Player::class, ['source' => 'universal', 'key' => 'awaken-wonder', 'piece' => 'itp'])
        ->call('finish')
        ->assertDispatched('player-navigate', url: $url, direction: 'back');

    expect(Cookie::hasQueued(Welcome::COOKIE))->toBeFalse();
})->with([
    'an ITP\'s page' => [[], '/universal/awaken-wonder'],
    'Today' => [['from' => 'today'], '/'],
    'a welcome that isn\'t the Introduction\'s' => [['from' => 'welcome'], '/universal/awaken-wonder'],
]);

it('plays the Introduction from the welcome with only a skip, then marks the device welcomed and opens Today', function () {
    playerIntroduction();

    $this->get('/play/universal/introduction/itp?from=welcome')
        ->assertOk()
        ->assertSee('skip intro?')
        ->assertSee('class="player-mark"', escape: false)
        ->assertDontSee('aria-label="Restart"', escape: false);

    Livewire::withQueryParams(['from' => 'welcome'])
        ->test(Player::class, ['source' => 'universal', 'key' => 'introduction', 'piece' => 'itp'])
        ->call('finish')
        ->assertDispatched('player-navigate', url: '/', direction: 'replace');

    expect(Welcome::seen())->toBeTrue();
});
