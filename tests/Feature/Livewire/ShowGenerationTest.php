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
 * An attunement in two sections. The second can be mid-rewrite, or have a rewrite waiting.
 *
 * @return array{id: int, version: int, sections: list<array<string, mixed>>}
 */
function sampleAttunement(bool $secondIsRegenerating = false, bool $secondHasProposal = false): array
{
    return ['id' => 3, 'version' => 1, 'sections' => [
        [
            'part' => 1,
            'segments' => [['id' => 40, 'order' => 1, 'text' => 'Beloved Ann, the studio is already waiting for you.']],
            'regenerating' => false,
            'proposal' => null,
            'pinpointed_segment_id' => null,
        ],
        [
            'part' => 3,
            'segments' => [
                ['id' => 41, 'order' => 1, 'text' => 'Every lease you sign is a yes to your hands.'],
                ['id' => 42, 'order' => 2, 'text' => 'The kiln hums your name.'],
            ],
            'regenerating' => $secondIsRegenerating,
            'proposal' => $secondHasProposal ? ['flag_id' => 9, 'segments' => [
                ['id' => 77, 'order' => 1, 'text' => 'Your hands already know the way to the wheel.'],
            ]] : null,
            'pinpointed_segment_id' => $secondIsRegenerating || $secondHasProposal ? 41 : null,
        ],
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
    'regenerating' => [['status' => 'regenerating', 'attunement' => sampleAttunement(secondIsRegenerating: true)], 'Rewriting this part…', true],
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

it('shows each section as a passage, without part labels', function () {
    app(TokenStore::class)->put('1|secret');
    Http::fake([GENERATION_URL => generationResponse(['status' => 'awaiting_resonance', 'attunement' => sampleAttunement()])]);

    Livewire::actingAs(User::factory()->create())
        ->test(ShowGeneration::class, ['generationId' => GENERATION_ID])
        ->assertSeeInOrder(['Beloved Ann, the studio is already waiting for you.', 'This doesn’t resonate', 'Every lease you sign is a yes to your hands.', 'The kiln hums your name.', 'This doesn’t resonate', 'Accept'])
        ->assertSeeHtml('wire:click="startFlag(1)"')
        ->assertSeeHtml('wire:click="startFlag(3)"')
        ->assertDontSee('Opening')
        ->assertDontSee('Essence');
});

it('flags a section that doesn\'t resonate', function (?int $segmentId, string $requestedChange, ?string $sentChange) {
    app(TokenStore::class)->put('1|secret');
    Http::fake([
        GENERATION_URL.'/sections/3/flags' => generationResponse(['status' => 'regenerating', 'attunement' => sampleAttunement(secondIsRegenerating: true)], 202),
        GENERATION_URL => generationResponse(['status' => 'awaiting_resonance', 'attunement' => sampleAttunement()]),
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test(ShowGeneration::class, ['generationId' => GENERATION_ID])
        ->call('startFlag', 3)
        ->assertSee(['What doesn’t resonate?', 'Which line bothered you most?'])
        ->set('user_explanation', ' I haven\'t found the space yet ')
        ->set('requested_change', $requestedChange)
        ->set('segment_id', $segmentId)
        ->call('flag')
        ->assertHasNoErrors()
        ->assertSee('Rewriting this part…')
        ->assertSeeHtml('wire:poll.3s="refresh"')
        ->assertSet('flaggingPart', null)
        ->assertSet('segment_id', null);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://'.GENERATION_URL.'/sections/3/flags'
        && $request->data() === ['user_explanation' => 'I haven\'t found the space yet', 'requested_change' => $sentChange, 'segment_id' => $segmentId]);
})->with([
    'the whole section' => [null, '', null],
    'pointing at one line' => [42, 'Something about my hands', 'Something about my hands'],
]);

it('needs an explanation before flagging a section', function () {
    app(TokenStore::class)->put('1|secret');
    Http::fake([GENERATION_URL => generationResponse(['status' => 'awaiting_resonance', 'attunement' => sampleAttunement()])]);

    Livewire::actingAs(User::factory()->create())
        ->test(ShowGeneration::class, ['generationId' => GENERATION_ID])
        ->call('startFlag', 1)
        ->call('flag')
        ->assertHasErrors(['user_explanation' => 'required'])
        ->assertSee('The explanation field is required.');

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/flags'));
});

it('only lets the user point at a line in the section they\'re flagging', function () {
    app(TokenStore::class)->put('1|secret');
    Http::fake([GENERATION_URL => generationResponse(['status' => 'awaiting_resonance', 'attunement' => sampleAttunement()])]);

    Livewire::actingAs(User::factory()->create())
        ->test(ShowGeneration::class, ['generationId' => GENERATION_ID])
        ->call('startFlag', 3)
        ->set('user_explanation', 'Too much')
        ->set('segment_id', 40)
        ->call('flag')
        ->assertHasErrors(['segment_id' => 'in']);

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/flags'));
});

it('keeps polling while a section is rewritten, without offering to accept or flag', function () {
    app(TokenStore::class)->put('1|secret');
    Http::fake([GENERATION_URL => Http::sequence()
        ->push(['message' => 'OK', 'errors' => [], 'data' => ['generation' => ['id' => GENERATION_ID, 'status' => 'regenerating', 'itp_id' => null, 'attunement' => sampleAttunement(secondIsRegenerating: true)]]])
        ->push(['message' => 'OK', 'errors' => [], 'data' => ['generation' => ['id' => GENERATION_ID, 'status' => 'regenerating', 'itp_id' => null, 'attunement' => sampleAttunement(secondIsRegenerating: true)]]])
        ->push(['message' => 'OK', 'errors' => [], 'data' => ['generation' => ['id' => GENERATION_ID, 'status' => 'awaiting_resonance', 'itp_id' => null, 'attunement' => sampleAttunement(secondHasProposal: true)]]]),
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test(ShowGeneration::class, ['generationId' => GENERATION_ID])
        ->assertSee(['Beloved Ann, the studio is already waiting for you.', 'Rewriting this part…', 'You can accept once it’s ready.'])
        ->assertSeeHtml('wire:poll.3s="refresh"')
        ->assertDontSeeHtml('wire:click="accept"')
        ->assertDontSeeHtml('wire:click="startFlag')
        ->call('refresh')
        ->assertSeeHtml('wire:poll.3s="refresh"')
        ->call('refresh')
        ->assertSee('New version')
        ->assertDontSeeHtml('wire:poll');
});

it('offers the rewritten section next to the original, and holds Accept until the user chooses', function () {
    app(TokenStore::class)->put('1|secret');
    Http::fake([GENERATION_URL => generationResponse(['status' => 'awaiting_resonance', 'attunement' => sampleAttunement(secondHasProposal: true)])]);

    Livewire::actingAs(User::factory()->create())
        ->test(ShowGeneration::class, ['generationId' => GENERATION_ID])
        ->assertSeeInOrder(['New version', 'Your hands already know the way to the wheel.', 'Use this', 'Your original', 'Every lease you sign is a yes to your hands.', 'The kiln hums your name.', 'Keep the original'])
        ->assertSee('Choose a version for the rewritten part first')
        ->assertDontSeeHtml('wire:click="accept"')
        ->assertDontSeeHtml('wire:click="startFlag(3)"');
});

it('sends the user\'s choice between the versions', function (string $button, string $keep) {
    app(TokenStore::class)->put('1|secret');
    Http::fake([
        GENERATION_URL.'/sections/3/choice' => generationResponse(['status' => 'awaiting_resonance', 'attunement' => sampleAttunement()]),
        GENERATION_URL => generationResponse(['status' => 'awaiting_resonance', 'attunement' => sampleAttunement(secondHasProposal: true)]),
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test(ShowGeneration::class, ['generationId' => GENERATION_ID])
        ->assertSeeHtml($button)
        ->call('choose', 3, $keep)
        ->assertHasNoErrors()
        ->assertDontSee('New version')
        ->assertSeeHtml('wire:click="accept"');

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'https://'.GENERATION_URL.'/sections/3/choice'
        && $request->data() === ['keep' => $keep]);
})->with([
    'use this' => ['wire:click="choose(3, \'new\')"', 'new'],
    'keep the original' => ['wire:click="choose(3, \'original\')"', 'original'],
]);

it('shows the server\'s refusal beside the action', function (Closure $act, string $endpoint, array $attunement) {
    app(TokenStore::class)->put('1|secret');
    Http::fake([
        GENERATION_URL.$endpoint => Http::response(['message' => 'This part is already being rewritten.', 'data' => (object) [], 'errors' => (object) []], 422),
        GENERATION_URL => generationResponse(['status' => 'awaiting_resonance', 'attunement' => $attunement]),
    ]);

    $screen = Livewire::actingAs(User::factory()->create())
        ->test(ShowGeneration::class, ['generationId' => GENERATION_ID]);

    $act($screen)
        ->assertSee('This part is already being rewritten.')
        ->assertSet('generation.attunement', $attunement);
})->with([
    'flagging' => [fn ($screen) => $screen->call('startFlag', 3)->set('user_explanation', 'Too much')->call('flag')->assertSee('Rewrite this part'), '/sections/3/flags', sampleAttunement()],
    'choosing' => [fn ($screen) => $screen->call('choose', 3, 'new')->assertSee('Use this'), '/sections/3/choice', sampleAttunement(secondHasProposal: true)],
    'accepting' => [fn ($screen) => $screen->call('accept')->assertSee('Accept'), '/accept', sampleAttunement()],
]);

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
