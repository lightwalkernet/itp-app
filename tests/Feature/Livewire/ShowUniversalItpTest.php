<?php

use App\Livewire\ShowUniversalItp;
use App\Media\MediaDownload;
use App\Media\MediaDownloadStatus;
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

it('shows a guest a preview, with its bundled audio', function () {
    $itp = UniversalItp::factory()->preview()->create([
        'short_name' => 'Awaken Wonder',
        'title' => 'Light Up Your Life',
        'description' => "Feeling weighed down by the everyday?\nReconnect with magic.",
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
        ->assertSee('data-room="white"', escape: false)
        ->assertSeeInOrder([
            'href="/itps" data-transition="back"',
            '<img src="'.asset('media/images/universals/original/1-awaken-wonder.webp').'" alt="" class="emblem">',
            '<p class="t-kicker itp-kicker">Awaken Wonder</p>',
            'Light Up Your Life',
            '<i class="itp-desc__ask">Feeling weighed down by the everyday?</i>',
            'Reconnect with magic.',
            'Enter',
            '<audio controls preload="none" src="'.asset('media/audio/universals/activations/1-awaken-wonder.mp3').'"',
            'Breathe 11',
            'A Quantum Why',
            'The words',
            "<p class=\"whitespace-pre-line o\">Beloved Dreamer\nHave you stopped believing?</p>",
            '<p class="whitespace-pre-line">Remember Dear One</p>',
            'The eleven',
            'src="'.asset('media/audio/universals/breath11/1-awaken-wonder.mp3').'"',
            'I breathe in wonder.',
            'I breathe out the doubt.',
            'Quantum Whys',
            '<p class="o" wire:key="why-0">Why is life so magical?</p>',
        ], escape: false)
        ->assertDontSee('Live with this one');
});

it('lets a guest read a locked ITP, and asks them to sign in to listen', function () {
    UniversalItp::factory()->preview()->count(4)->create();
    $itp = UniversalItp::factory()->create([
        'attunement_text' => 'Beloved One, the way is open.',
        'attunement_audio_path' => 'media/audio/universals/activations/1-awaken-wonder.mp3',
    ]);

    $this->get(route('universals.show', $itp))
        ->assertOk()
        ->assertSee('Beloved One, the way is open.')
        ->assertSee('<a href="/sign-in" data-transition="forward" class="btn">Sign in to listen', escape: false)
        ->assertSee('email and a code')
        ->assertSee('You can read the words below first. Four Universals are open to everyone; this one waits for you after sign-in.')
        ->assertDontSee('<audio', escape: false);
});

it('opens every ITP once signed in, streaming audio the app doesn\'t bundle on the web', function () {
    $itp = UniversalItp::factory()->create(['attunement_audio_path' => 'media/audio/universals/activations/4-purpose.mp3']);

    $this->actingAs(User::factory()->create())
        ->get(route('universals.show', $itp))
        ->assertOk()
        ->assertSee('<audio controls preload="none" src="https://media.itp.test/media/audio/universals/activations/4-purpose.mp3"', escape: false)
        ->assertDontSee('Sign in to listen');
});

it('shows where each audio stands on the phone', function (?MediaDownloadStatus $download, bool $downloaded, array $expected) {
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
        ->assertSeeInOrder($expected, escape: false);
})->with([
    'downloaded' => [MediaDownloadStatus::Downloaded, true, ['src="/media/media/audio/universals/activations/4-purpose.mp3"']],
    'not downloaded yet' => [null, false, ['class="btn" disabled="disabled">Enter', '<small>downloading…</small>', '<p class="t-meta itp-status">Downloading…</p>']],
    'download failed' => [MediaDownloadStatus::Failed, false, ['class="btn" disabled="disabled">Enter', '<small>waiting for a connection</small>', '<p class="t-meta itp-status">Downloads when you’re online.</p>']],
]);

it('asks a guest on the phone to sign in for preview audio the app doesn\'t have', function () {
    useMobileBuild()->items = [];
    $itp = UniversalItp::factory()->preview()->create(['attunement_audio_path' => 'media/audio/universals/activations/1-awaken-wonder.v2.mp3']);

    $this->get(route('universals.show', $itp))
        ->assertOk()
        ->assertSee('href="/sign-in" data-transition="forward"', escape: false)
        ->assertSee('Sign in to listen')
        ->assertDontSee('You can read the words below first.')
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

it('lets a guest open the Introduction, which has audio and words but nothing else', function () {
    $introduction = UniversalItp::factory()->base()->preview()->create([
        'short_name' => 'Introduction',
        'title' => 'Introduction',
        'description' => null,
        'image_path' => null,
        'attunement_text' => 'Welcome, beloved.',
        'attunement_audio_path' => 'media/audio/base/introduction.mp3',
        'breath11_lines' => null,
        'breath11_timings' => null,
        'breath11_audio_path' => null,
        'quantum_whys' => null,
    ]);

    $this->get(route('universals.show', $introduction))
        ->assertOk()
        ->assertSee('Welcome, beloved.')
        ->assertSee('src="'.asset('media/audio/base/introduction.mp3').'"', escape: false)
        ->assertDontSee('class="emblem"', escape: false)
        ->assertDontSee('itp-kicker', escape: false)
        ->assertDontSee('The eleven')
        ->assertDontSee('Quantum Whys')
        ->assertDontSee('aria-disabled="true"', escape: false);
});

it('leaves out every fold with nothing in it, as on an audio-only primer', function () {
    $primer = UniversalItp::factory()->primer()->preview()->create([
        'attunement_text' => null,
        'attunement_audio_path' => 'media/audio/primers/return-to-coherence.mp3',
        'breath11_lines' => null,
        'breath11_audio_path' => null,
        'quantum_whys' => null,
    ]);

    $this->get(route('universals.show', $primer))
        ->assertOk()
        ->assertSee('Enter')
        ->assertDontSee('class="fold"', escape: false)
        ->assertDontSee('aria-disabled="true"', escape: false);
});

it('ships the Introduction and the four previews as playable for guests', function () {
    $snapshot = json_decode(file_get_contents(resource_path('content/snapshot.json')), true, flags: JSON_THROW_ON_ERROR);

    $playable = array_column(array_filter($snapshot['types']['universal_itps'], fn (array $row) => (bool) $row['is_preview']), 'slug');

    expect($playable)->toEqualCanonicalizing(['awaken-wonder', 'abundance', 'healing', 'nervous-system-reset', 'introduction']);
});

it('lives with a universal ITP instead, then says so', function () {
    $user = User::factory()->create();
    $current = UniversalItp::factory()->create();
    UserSetting::factory()->for($user)->livingWith($current)->create();
    $itp = UniversalItp::factory()->create();

    Livewire::actingAs($user)
        ->test(ShowUniversalItp::class, ['universalItp' => $itp])
        ->assertSeeHtml('wire:click="liveWithThis"')
        ->assertDontSee('You’re living with this one.')
        ->call('liveWithThis')
        ->assertSee('You’re living with this one.')
        ->assertDontSeeHtml('wire:click="liveWithThis"');

    expect($user->settings()->sole())
        ->living_with_type->toBe(LivingWithType::Universal)
        ->living_with_id->toBe($itp->id);
});

it('creates the settings row when choosing, if sign-in somehow left none', function () {
    $user = User::factory()->create();
    $itp = UniversalItp::factory()->create();

    Livewire::actingAs($user)
        ->test(ShowUniversalItp::class, ['universalItp' => $itp])
        ->call('liveWithThis');

    expect($user->settings()->sole()->living_with_id)->toBe($itp->id);
});

it('says Meet Your ITP Self is lived with until another is chosen, and offers it back afterwards', function () {
    $user = User::factory()->create();
    $meetYourItpSelf = UniversalItp::factory()->base()->create(['slug' => 'meet-your-itp-self']);

    Livewire::actingAs($user)
        ->test(ShowUniversalItp::class, ['universalItp' => $meetYourItpSelf])
        ->assertSee('You’re living with this one.');

    UserSetting::factory()->for($user)->livingWith(UniversalItp::factory()->create())->create();

    Livewire::actingAs($user->fresh())
        ->test(ShowUniversalItp::class, ['universalItp' => $meetYourItpSelf])
        ->assertSeeHtml('wire:click="liveWithThis"');
});

it('never offers living with a primer or the Introduction', function (string $kind, string $slug) {
    $user = User::factory()->create();
    $itp = UniversalItp::factory()->create(['kind' => $kind, 'slug' => $slug]);

    Livewire::actingAs($user)
        ->test(ShowUniversalItp::class, ['universalItp' => $itp])
        ->assertDontSee('Live with this one')
        ->assertDontSee('You’re living with this one.')
        ->call('liveWithThis')
        ->assertForbidden();

    expect($user->settings()->exists())->toBeFalse();
})->with([
    'a primer' => ['primer', 'return-to-coherence'],
    'the Introduction' => ['base', 'introduction'],
]);

it('never offers living with to a guest', function () {
    $itp = UniversalItp::factory()->preview()->create();

    Livewire::test(ShowUniversalItp::class, ['universalItp' => $itp])
        ->assertDontSee('Live with this one')
        ->call('liveWithThis')
        ->assertForbidden();
});
