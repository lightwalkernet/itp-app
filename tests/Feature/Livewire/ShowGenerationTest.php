<?php

use App\Auth\TokenStore;
use App\Livewire\ShowGeneration;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Itp\Core\Models\Itp;
use Itp\Core\Models\User;
use Livewire\Livewire;

const GENERATION_ID = '01jc0000000000000000000001';
const GENERATION_URL = 'api.itp.test/v1/generations/01jc0000000000000000000001';

beforeEach(function () {
    Http::preventStrayRequests();
    $this->withoutVite();
});

/**
 * @return array{id: int, version: int, segments: list<array<string, mixed>>}
 */
function sampleAttunement(bool $secondIsRegenerating = false): array
{
    return ['id' => 3, 'version' => 1, 'segments' => [
        ['id' => 40, 'order' => 1, 'text' => 'Beloved Ann, the studio is already waiting for you.'],
        ['id' => 41, 'order' => 2, 'text' => 'Every lease you sign is a yes to your hands.'] + ($secondIsRegenerating ? ['regenerating' => true] : []),
    ]];
}

it('shows each status, polling only while the server is working', function (array $generation, string $text, bool $polls) {
    app(TokenStore::class)->put('1|secret');
    Http::fake([GENERATION_URL => generationResponse($generation)]);

    $screen = Livewire::actingAs(User::factory()->create())
        ->test(ShowGeneration::class, ['generationId' => GENERATION_ID])
        ->assertSee($text);

    $polls
        ? $screen->assertSeeHtml('wire:poll.3s="refresh"')
        : $screen->assertDontSeeHtml('wire:poll');
})->with([
    'queued' => [['status' => 'queued'], 'Getting started…', true],
    'assessing' => [['status' => 'assessing'], 'Taking in what you shared…', true],
    'generating' => [['status' => 'generating'], 'Writing your attunement…', true],
    'regenerating' => [['status' => 'regenerating', 'attunement' => sampleAttunement(secondIsRegenerating: true)], 'Being rewritten…', true],
    'deriving companions' => [['status' => 'deriving_companions', 'attunement' => sampleAttunement()], 'Creating your Breathe 11 and Quantum Whys…', true],
    'a stage this app doesn\'t know yet' => [['status' => 'polishing'], 'Working on it…', true],
    'awaiting follow-up' => [['status' => 'awaiting_followup', 'followups' => [['id' => 12, 'round' => 1, 'question_text' => 'Who will your first students be?']]], 'Who will your first students be?', false],
    'awaiting resonance' => [['status' => 'awaiting_resonance', 'attunement' => sampleAttunement()], 'Every lease you sign is a yes to your hands.', false],
    'deferred' => [['status' => 'deferred', 'message' => 'We’ll be in touch about this one.'], 'We’ll be in touch about this one.', false],
    'blocked' => [['status' => 'blocked', 'message' => 'This isn’t something an ITP can help with.'], 'This isn’t something an ITP can help with.', false],
    'failed' => [['status' => 'failed', 'message' => 'Something went wrong on our side.'], 'Something went wrong on our side.', false],
]);

it('sends the follow-up answers, then waits for the server again', function () {
    app(TokenStore::class)->put('1|secret');
    Http::fake([
        GENERATION_URL.'/followups' => generationResponse(['status' => 'assessing'], 202),
        GENERATION_URL => generationResponse(['status' => 'awaiting_followup', 'followups' => [
            ['id' => 12, 'round' => 1, 'question_text' => 'Who will your first students be?'],
            ['id' => 13, 'round' => 1, 'question_text' => 'What does the space smell like?'],
        ]]),
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test(ShowGeneration::class, ['generationId' => GENERATION_ID])
        ->set('answers.12', ' Neighbours from my street ')
        ->call('answerFollowups')
        ->assertHasNoErrors()
        ->assertSee('Taking in what you shared…')
        ->assertSeeHtml('wire:poll.3s="refresh"');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://'.GENERATION_URL.'/followups'
        && $request->data() === ['answers' => [
            ['followup_id' => 12, 'answer_text' => 'Neighbours from my street'],
            ['followup_id' => 13, 'answer_text' => null],
        ]]);
});

it('flags a segment that doesn\'t resonate', function () {
    app(TokenStore::class)->put('1|secret');
    Http::fake([
        GENERATION_URL.'/segments/41/flags' => generationResponse(['status' => 'regenerating', 'attunement' => sampleAttunement(secondIsRegenerating: true)], 202),
        GENERATION_URL => generationResponse(['status' => 'awaiting_resonance', 'attunement' => sampleAttunement()]),
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test(ShowGeneration::class, ['generationId' => GENERATION_ID])
        ->call('startFlag', 41)
        ->assertSee('What doesn’t resonate?')
        ->set('user_explanation', 'I haven\'t found the space yet')
        ->call('flag')
        ->assertHasNoErrors()
        ->assertSee('Being rewritten…')
        ->assertSet('flaggingSegmentId', null);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://'.GENERATION_URL.'/segments/41/flags'
        && $request->data() === ['user_explanation' => 'I haven\'t found the space yet', 'requested_change' => null]);
});

it('needs an explanation before flagging a segment', function () {
    app(TokenStore::class)->put('1|secret');
    Http::fake([GENERATION_URL => generationResponse(['status' => 'awaiting_resonance', 'attunement' => sampleAttunement()])]);

    Livewire::actingAs(User::factory()->create())
        ->test(ShowGeneration::class, ['generationId' => GENERATION_ID])
        ->call('startFlag', 40)
        ->call('flag')
        ->assertHasErrors(['user_explanation' => 'required'])
        ->assertSee('The explanation field is required.');

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/flags'));
});

it('accepts the attunement', function () {
    app(TokenStore::class)->put('1|secret');
    Http::fake([
        GENERATION_URL.'/accept' => generationResponse(['status' => 'deriving_companions', 'attunement' => sampleAttunement()], 202),
        GENERATION_URL => generationResponse(['status' => 'awaiting_resonance', 'attunement' => sampleAttunement()]),
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test(ShowGeneration::class, ['generationId' => GENERATION_ID])
        ->call('accept')
        ->assertSee('Creating your Breathe 11 and Quantum Whys…');

    Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request->url() === 'https://'.GENERATION_URL.'/accept');
});

it('picks up the server\'s progress when polled', function () {
    app(TokenStore::class)->put('1|secret');
    Http::fake([GENERATION_URL => Http::sequence()
        ->push(['message' => 'OK', 'errors' => [], 'data' => ['generation' => ['id' => GENERATION_ID, 'status' => 'generating', 'itp_id' => null]]])
        ->push(['message' => 'OK', 'errors' => [], 'data' => ['generation' => ['id' => GENERATION_ID, 'status' => 'awaiting_resonance', 'itp_id' => null, 'attunement' => sampleAttunement()]]]),
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test(ShowGeneration::class, ['generationId' => GENERATION_ID])
        ->assertSee('Writing your attunement…')
        ->call('refresh')
        ->assertSee('This doesn’t resonate')
        ->assertDontSeeHtml('wire:poll');
});

it('links to the finished ITP by path on the web, without syncing', function () {
    $user = User::factory()->create();
    $itp = Itp::factory()->for($user)->create();
    $this->actingAs($user);
    app(TokenStore::class)->put('1|secret');
    Http::fake([GENERATION_URL => generationResponse(['status' => 'complete', 'itp_id' => $itp->id, 'title' => $itp->title])]);

    $this->get(route('generations.show', GENERATION_ID))
        ->assertOk()
        ->assertSee('Your ITP is ready')
        ->assertSee('href="/itps/'.$itp->id.'" data-transition="forward"', escape: false);

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/sync/'));
});

it('syncs the finished ITP onto the phone', function () {
    useMobileBuild();
    $user = User::factory()->create();
    $itpId = '01jc00000000000000000000aa';
    Http::fake([
        GENERATION_URL => generationResponse(['status' => 'complete', 'itp_id' => $itpId, 'title' => 'The studio is open']),
        'api.itp.test/v1/sync/pull*' => syncPullResponder(['itps' => [[
            'id' => $itpId,
            'user_id' => $user->id,
            'title' => 'The studio is open',
            'description' => 'For the studio in Nelson.',
            'attunement_text' => "Beloved Ann.\n\nThe studio is waiting.",
            'breath11_lines' => '[{"order":1,"text":"I breathe in the clay."}]',
            'quantum_whys' => '["Why is it so easy to open my studio?"]',
            'sync_seq' => 7,
            'deleted_at' => null,
            'created_at' => '2026-11-07 09:10:00',
            'updated_at' => '2026-11-07 09:10:00',
        ]]]),
    ]);

    Livewire::actingAs($user)
        ->test(ShowGeneration::class, ['generationId' => GENERATION_ID])
        ->assertSee('Open my ITP');

    expect(Itp::find($itpId)?->title)->toBe('The studio is open');
    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://api.itp.test/v1/sync/pull?type=itps')
        && $request->header('Authorization') === ['Bearer 1|secret']
        && $request->header('X-App-Version') === ['1.4.2']);
});

it('offers a refresh when the finished ITP couldn\'t reach the phone', function () {
    useMobileBuild();
    config(['sync.client.retries' => 0]);
    Http::fake([
        GENERATION_URL => generationResponse(['status' => 'complete', 'itp_id' => '01jc00000000000000000000aa']),
        'api.itp.test/v1/sync/pull*' => fn () => throw new ConnectionException('Offline'),
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test(ShowGeneration::class, ['generationId' => GENERATION_ID])
        ->assertOk()
        ->assertSee('It isn’t on this device yet.')
        ->assertSeeHtml('wire:click="refresh"')
        ->assertDontSee('Open my ITP');
});

it('offers to try again when the API can\'t be reached', function () {
    app(TokenStore::class)->put('1|secret');
    Http::fake([GENERATION_URL => fn () => throw new ConnectionException('Offline')]);

    Livewire::actingAs(User::factory()->create())
        ->test(ShowGeneration::class, ['generationId' => GENERATION_ID])
        ->assertSee("We can't reach it's totally possible right now.")
        ->assertSee('Try again');
});

it('is not found when the generation belongs to someone else', function () {
    $this->actingAs(User::factory()->create());
    app(TokenStore::class)->put('1|secret');
    Http::fake([GENERATION_URL => Http::response(['message' => 'Not found.', 'data' => (object) [], 'errors' => (object) []], 404)]);

    $this->get(route('generations.show', GENERATION_ID))->assertNotFound();
});
