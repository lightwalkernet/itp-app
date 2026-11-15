<?php

use App\Livewire\Library;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Itp\Core\Models\Itp;
use Itp\Core\Models\UniversalItp;
use Itp\Core\Models\User;
use Itp\Core\Models\UserSetting;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    Http::preventStrayRequests();
});

it('lists the universal ITPs in their order, counting each tab from the data', function () {
    UniversalItp::factory()->create(['short_name' => 'Purpose', 'title' => 'Uncover Your Purpose', 'sort_order' => 2]);
    UniversalItp::factory()->create(['short_name' => 'Awaken Wonder', 'title' => 'Light Up Your Life', 'sort_order' => 1, 'image_path' => 'media/images/universals/original/1-awaken-wonder.webp']);
    UniversalItp::factory()->base()->create(['short_name' => 'Introduction']);
    UniversalItp::factory()->primer()->create(['slug' => 'return-to-coherence']);
    $user = User::factory()->create();
    Itp::factory()->for($user)->count(3)->create();

    $this->actingAs($user)
        ->get(route('itps.index'))
        ->assertOk()
        ->assertSeeLivewire(Library::class)
        ->assertSee('data-room="white"', escape: false)
        ->assertSeeInOrder([
            'Everything you can enter',
            'Your ITPs',
            'Universal <small>2</small>',
            'Personal <small>3</small>',
            'Primers <small>1</small>',
            'Two, for everyone. Live with one for a while, or just listen to one today — that doesn’t change what you’re living with.',
            '<img src="'.asset('media/images/universals/original/1-awaken-wonder.webp').'" alt="" class="lrow__thumb"',
            '<span class="lrow__name">Awaken Wonder</span>',
            '<span class="lrow__meta">Light Up Your Life</span>',
            '<span class="lrow__side">3 min</span>',
            'Purpose',
        ], escape: false)
        ->assertDontSee('Introduction');
});

it('says thirteen in words when there are thirteen', function () {
    UniversalItp::factory()->count(13)->create();

    $this->get(route('itps.index'))->assertSee('Thirteen, for everyone.');
});

it('shows a guest the non-previews locked, still leading to their page', function () {
    UniversalItp::factory()->preview()->count(3)->create();
    $locked = UniversalItp::factory()->create(['short_name' => 'Purpose']);

    $this->get(route('itps.index'))
        ->assertOk()
        ->assertSee('Four, for everyone. Three are open before you sign in; the rest wait for you after.')
        ->assertSee('Personal <small></small>', escape: false)
        ->assertSeeInOrder([
            'href="/universal/'.$locked->slug.'" data-transition="forward" class="lrow lrow--locked"',
            'Purpose',
            '<span class="lrow__side">sign in</span>',
        ], escape: false);
});

it('invites a guest to sign in on the Personal tab', function () {
    Livewire::test(Library::class)
        ->call('selectTab', 'personal')
        ->assertSee('Made from your words, for you alone.')
        ->assertSee('Your own ITP is made from your words — a few questions, answered in your own time.')
        ->assertSee('After you sign in. Email and a six-digit code; no password.')
        ->assertSeeHtml('href="/sign-in" data-transition="forward" class="btn">Sign in')
        ->assertDontSee('Make a new personal ITP');
});

it('lists the user\'s own ITPs, newest first, marking the one they live with', function () {
    $user = User::factory()->create();
    $older = Itp::factory()->for($user)->create(['title' => 'The studio is open', 'created_at' => '2026-11-02 09:00:00']);
    Itp::factory()->for($user)->create(['title' => 'The quiet yes', 'created_at' => '2026-11-12 09:00:00']);
    Itp::factory()->create(['title' => 'Someone else’s ITP']);
    UserSetting::factory()->for($user)->livingWith($older)->create();

    Livewire::actingAs($user)
        ->withQueryParams(['tab' => 'personal'])
        ->test(Library::class)
        ->assertSeeInOrder([
            'The quiet yes',
            'Made from your words · 12 November</span>',
            'href="/itps/'.$older->id.'" data-transition="forward"',
            'The studio is open',
            'Made from your words · 2 November · living with</span>',
            'href="/itps/create" data-transition="forward" class="btn btn--ghost">Make a new personal ITP',
            'about ten minutes',
        ], escape: false)
        ->assertDontSee('Someone else’s ITP');
});

it('keeps the tab in the URL', function () {
    Livewire::withQueryParams(['tab' => 'primers'])
        ->test(Library::class)
        ->assertSet('tab', 'primers')
        ->assertSee('For a moment, not a day.')
        ->call('selectTab', 'universal')
        ->assertSet('tab', 'universal');

    $this->get(route('itps.index', ['tab' => 'personal']))->assertSee('Made from your words, for you alone.');
});

it('falls back to the Universal tab for a tab that doesn\'t exist', function () {
    Livewire::withQueryParams(['tab' => 'nonsense'])
        ->test(Library::class)
        ->assertSee('for everyone.')
        ->call('selectTab', 'nonsense')
        ->assertSet('tab', 'universal');
});

it('shows the two primers as coming soon until their rows exist', function () {
    Livewire::withQueryParams(['tab' => 'primers'])
        ->test(Library::class)
        ->assertSeeInOrder([
            'For a moment, not a day. Breathe 11s and Quantum Whys aren’t here — they belong to the ITP you’re living with.',
            '<div class="lrow lrow--plain" aria-disabled="true"',
            'Return to Coherence',
            'coming soon',
            '<div class="lrow lrow--plain" aria-disabled="true"',
            'Highest Possible Outcome',
            'coming soon',
        ], escape: false)
        ->assertDontSeeHtml('href="/universal/');
});

it('links a primer to its page once its row exists', function () {
    UniversalItp::factory()->primer()->create(['slug' => 'return-to-coherence', 'short_name' => 'Return to Coherence']);

    Livewire::withQueryParams(['tab' => 'primers'])
        ->test(Library::class)
        ->assertSeeInOrder([
            'href="/universal/return-to-coherence" data-transition="forward" class="lrow lrow--plain"',
            'Return to Coherence',
            'for right now · 2 min',
            'aria-disabled="true"',
            'Highest Possible Outcome',
            'coming soon',
        ], escape: false)
        ->assertSee('Primers <small>1</small>', escape: false);
});

it('downloads missing audio on the phone while any is pending, one file per poll', function () {
    Storage::fake('media');
    config(['itp.media_url' => 'https://media.itp.test']);
    $keychain = useMobileBuild();
    $user = User::factory()->create();
    $keychain->items['itp.user_id'] = $user->id;
    UniversalItp::factory()->create(['attunement_audio_path' => 'media/audio/universals/activations/4-purpose.mp3', 'breath11_audio_path' => null]);
    Http::fake(['media.itp.test/*' => Http::response('mp3 bytes')]);

    Livewire::actingAs($user)
        ->test(Library::class)
        ->assertSeeHtml('wire:poll.3s="downloadNext"')
        ->call('downloadNext')
        ->assertDontSeeHtml('wire:poll');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://media.itp.test/media/audio/universals/activations/4-purpose.mp3');
    Storage::disk('media')->assertExists('media/audio/universals/activations/4-purpose.mp3');
});

it('never polls for downloads on the web or for a guest', function (bool $signedIn) {
    UniversalItp::factory()->create(['attunement_audio_path' => 'media/audio/universals/activations/4-purpose.mp3']);

    $library = $signedIn ? Livewire::actingAs(User::factory()->create())->test(Library::class) : Livewire::test(Library::class);

    $library->assertDontSeeHtml('wire:poll')->call('downloadNext');

    Http::assertNothingSent();
})->with(['signed in on the web' => true, 'a guest' => false]);
