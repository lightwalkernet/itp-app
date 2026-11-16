<?php

use App\Livewire\ShowUniversalItp;
use App\Media\MediaDownload;
use App\Media\MediaDownloadStatus;
use App\Support\GuestChoice;
use Illuminate\Support\Facades\Storage;
use Itp\Core\Enums\LivingWithType;
use Itp\Core\Models\UniversalItp;
use Itp\Core\Models\User;
use Itp\Core\Models\UserSetting;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('media');
    config(['itp.media_url' => 'https://media.itp.test']);
});

/**
 * Timings as the generation pipeline writes them: enough for the player to keep in step.
 *
 * @return list<array<string, mixed>>
 */
function itpPageTimings(): array
{
    return [['start' => 0.0, 'end' => 4.0, 'text' => 'I breathe in wonder.', 'words' => [['word' => 'I', 'start' => 0.0, 'end' => 0.4]]]];
}

it('shows a guest an open ITP: its short name, its description, Make this my ITP and its three pieces', function () {
    $itp = UniversalItp::factory()->preview()->create([
        'slug' => 'awaken-wonder',
        'short_name' => 'Awaken Wonder',
        'title' => 'Light Up Your Life',
        'description' => "Feeling weighed down by the everyday?\nReconnect with magic.",
        'image_path' => 'media/images/universals/original/1-awaken-wonder.webp',
        'attunement_text' => 'Beloved Dreamer, have you stopped believing?',
        'attunement_audio_path' => 'media/audio/universals/activations/1-awaken-wonder.mp3',
        'breath11_lines' => [['order' => 1, 'text' => 'I breathe in wonder.']],
        'breath11_timings' => itpPageTimings(),
        'breath11_audio_path' => 'media/audio/universals/breath11/1-awaken-wonder.mp3',
        'quantum_whys' => ['Why is life so magical?'],
    ]);

    $this->get(route('universals.show', $itp))
        ->assertOk()
        ->assertSeeLivewire(ShowUniversalItp::class)
        ->assertSee('<title>Awaken Wonder · ', escape: false)
        ->assertSee('data-room="white"', escape: false)
        ->assertSeeInOrder([
            'href="/itps" data-transition="back"',
            '<img src="'.asset('media/images/universals/original/1-awaken-wonder.webp').'" alt="" class="emblem">',
            '<h1 class="t-room itp-title">Awaken Wonder</h1>',
            '<i class="itp-desc__ask">Feeling weighed down by the everyday?</i>',
            'Reconnect with magic.',
            '<button type="button" class="btn" wire:click="makeThisMyItp">Make this my ITP',
            '<a href="/play/universal/awaken-wonder/itp" data-transition="forward"', 'data-piece="itp" wire:key="piece-itp">ITP', '<small>3 min</small>',
            '<a href="/play/universal/awaken-wonder/breathe" data-transition="forward"', '>Breathe 11', '<small>2 min</small>',
            '<a href="/play/universal/awaken-wonder/why" data-transition="forward"', '>Quantum Why', '<small>a moment</small>',
        ], escape: false)
        ->assertDontSee('Light Up Your Life')
        ->assertDontSee('Beloved Dreamer')
        ->assertDontSee('Why is life so magical?')
        ->assertDontSee('I breathe in wonder.')
        ->assertDontSee('<audio', escape: false)
        ->assertDontSee('class="fold"', escape: false)
        ->assertDontSee('?from=', escape: false);
});

it('asks a guest to sign in to play a locked ITP, with nothing to choose or play', function () {
    UniversalItp::factory()->preview()->count(4)->create();
    $itp = UniversalItp::factory()->create();

    $this->get(route('universals.show', $itp))
        ->assertOk()
        ->assertSeeInOrder(['<a href="/sign-in" data-transition="forward" class="btn">Sign in to play', '<small>email and a code</small>'], escape: false)
        ->assertSee('Four Universals are open to everyone; this one waits for you after sign-in.')
        ->assertDontSee('Make this my ITP')
        ->assertDontSee('href="/play/', escape: false);
});

it('opens every ITP once signed in, to choose and to play', function () {
    $itp = UniversalItp::factory()->create(['slug' => 'purpose']);

    $this->actingAs(User::factory()->create())
        ->get(route('universals.show', $itp))
        ->assertOk()
        ->assertSee('wire:click="makeThisMyItp"', escape: false)
        ->assertSee('href="/play/universal/purpose/itp"', escape: false)
        ->assertSee('href="/play/universal/purpose/why"', escape: false)
        ->assertDontSee('Sign in to play');
});

it('disables a piece that can\'t play on the phone yet, saying why', function (?MediaDownloadStatus $download, bool $downloaded, array $expected) {
    $keychain = useMobileBuild();
    $user = User::factory()->create();
    $keychain->items['itp.user_id'] = $user->id;
    $key = 'media/audio/universals/activations/4-purpose.mp3';
    $itp = UniversalItp::factory()->create(['slug' => 'purpose', 'attunement_audio_path' => $key]);

    if ($download !== null) {
        MediaDownload::create(['key' => $key, 'status' => $download]);
    }

    if ($downloaded) {
        Storage::disk('media')->put($key, 'mp3 bytes');
    }

    $this->get(route('universals.show', $itp))
        ->assertOk()
        ->assertSeeInOrder($expected, escape: false);
})->with([
    'downloaded' => [MediaDownloadStatus::Downloaded, true, ['<a href="/play/universal/purpose/itp" data-transition="forward"', '<small>3 min</small>']],
    'not downloaded yet' => [null, false, ['disabled="disabled" data-piece="itp"', '<small>downloading…</small>']],
    'download failed' => [MediaDownloadStatus::Failed, false, ['disabled="disabled" data-piece="itp"', '<small>waiting for a connection</small>']],
]);

it('disables Breathe 11 while it has no voice, leaving the other pieces playable', function () {
    $itp = UniversalItp::factory()->preview()->create([
        'slug' => 'healing',
        'breath11_lines' => [['order' => 1, 'text' => 'I breathe in.']],
        'breath11_timings' => null,
        'breath11_audio_path' => null,
    ]);

    $this->get(route('universals.show', $itp))
        ->assertOk()
        ->assertSeeInOrder([
            'href="/play/universal/healing/itp"',
            'disabled="disabled" data-piece="breathe"', '<small>no voice yet</small>',
            'href="/play/universal/healing/why"',
        ], escape: false);
});

it('tells a guest on the phone that preview audio the app doesn\'t have comes after sign-in', function () {
    useMobileBuild()->items = [];
    $itp = UniversalItp::factory()->preview()->create(['attunement_audio_path' => 'media/audio/universals/activations/1-awaken-wonder.v2.mp3']);

    $this->get(route('universals.show', $itp))
        ->assertOk()
        ->assertSeeInOrder(['disabled="disabled" data-piece="itp"', '<small>after sign-in</small>'], escape: false)
        ->assertDontSee('Sign in to play');
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

it('lets a guest play the Introduction, which has nothing to choose and no Breathe 11 or Whys', function () {
    $introduction = UniversalItp::factory()->base()->preview()->create([
        'slug' => 'introduction',
        'short_name' => 'Introduction',
        'description' => null,
        'image_path' => null,
        'attunement_audio_path' => 'media/audio/base/introduction.mp3',
        'breath11_lines' => null,
        'breath11_timings' => null,
        'breath11_audio_path' => null,
        'quantum_whys' => null,
    ]);

    $this->get(route('universals.show', $introduction))
        ->assertOk()
        ->assertSee('<a href="/play/universal/introduction/itp" data-transition="forward"', escape: false)
        ->assertSee('data-piece="itp" wire:key="piece-itp">Play', escape: false)
        ->assertDontSee('class="emblem"', escape: false)
        ->assertDontSee('Make this my ITP')
        ->assertDontSee('Breathe 11')
        ->assertDontSee('Quantum Why');
});

it('ships the Introduction and the four previews as playable for guests', function () {
    $snapshot = json_decode(file_get_contents(resource_path('content/snapshot.json')), true, flags: JSON_THROW_ON_ERROR);

    $playable = array_column(array_filter($snapshot['types']['universal_itps'], fn (array $row) => (bool) $row['is_preview']), 'slug');

    expect($playable)->toEqualCanonicalizing(['awaken-wonder', 'abundance', 'healing', 'nervous-system-reset', 'introduction']);
});

it('makes a universal ITP the user\'s, then says so', function () {
    $user = User::factory()->create();
    UserSetting::factory()->for($user)->livingWith(UniversalItp::factory()->create())->create();
    $itp = UniversalItp::factory()->create();

    Livewire::actingAs($user)
        ->test(ShowUniversalItp::class, ['universalItp' => $itp])
        ->assertDontSee('This is your ITP.')
        ->call('makeThisMyItp')
        ->assertSee('This is your ITP.')
        ->assertDontSeeHtml('wire:click="makeThisMyItp"');

    expect($user->settings()->sole())
        ->living_with_type->toBe(LivingWithType::Universal)
        ->living_with_id->toBe($itp->id);
});

it('creates the settings row when choosing, if sign-in somehow left none', function () {
    $user = User::factory()->create();
    $itp = UniversalItp::factory()->create();

    Livewire::actingAs($user)
        ->test(ShowUniversalItp::class, ['universalItp' => $itp])
        ->call('makeThisMyItp');

    expect($user->settings()->sole()->living_with_id)->toBe($itp->id);
});

it('offers Meet Your ITP Self to choose, though it is a base piece', function () {
    $meetYourItpSelf = UniversalItp::factory()->base()->create(['slug' => 'meet-your-itp-self']);

    Livewire::actingAs(User::factory()->create())
        ->test(ShowUniversalItp::class, ['universalItp' => $meetYourItpSelf])
        ->assertSeeHtml('wire:click="makeThisMyItp"');
});

it('never offers choosing a primer or the Introduction', function (string $kind, string $slug) {
    $user = User::factory()->create();
    $itp = UniversalItp::factory()->create(['kind' => $kind, 'slug' => $slug]);

    Livewire::actingAs($user)
        ->test(ShowUniversalItp::class, ['universalItp' => $itp])
        ->assertDontSee('Make this my ITP')
        ->assertDontSee('This is your ITP.')
        ->call('makeThisMyItp')
        ->assertForbidden();

    expect($user->settings()->exists())->toBeFalse();
})->with([
    'a primer' => ['primer', 'return-to-coherence'],
    'the Introduction' => ['base', 'introduction'],
]);

it('lets a guest make an open ITP theirs, on this device only', function () {
    $itp = UniversalItp::factory()->preview()->create(['slug' => 'awaken-wonder']);

    Livewire::test(ShowUniversalItp::class, ['universalItp' => $itp])
        ->call('makeThisMyItp')
        ->assertSee('This is your ITP.')
        ->assertDontSeeHtml('wire:click="makeThisMyItp"');

    expect(GuestChoice::get()?->is($itp))->toBeTrue();
    $this->assertDatabaseCount('user_settings', 0);
});

it('never lets a guest choose an ITP that is locked before sign-in', function () {
    $itp = UniversalItp::factory()->create();

    Livewire::test(ShowUniversalItp::class, ['universalItp' => $itp])
        ->assertDontSee('Make this my ITP')
        ->call('makeThisMyItp')
        ->assertForbidden();

    expect(GuestChoice::get())->toBeNull();
});
