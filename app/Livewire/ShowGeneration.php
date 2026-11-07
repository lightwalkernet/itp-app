<?php

namespace App\Livewire;

use App\Api\GenerationClient;
use App\Api\GenerationStatus;
use App\Livewire\Concerns\CallsApi;
use App\Sync\SyncNow;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * One generation, as itp-server reports it. It always goes through the API, never the pipeline
 * tables (platform.md §4, §6). The page polls while the server is working, and asks the user
 * when it is waiting on them: follow-up answers, then whether each section of the attunement
 * resonates (a section that doesn't is rewritten, and the user picks the new or original version). Once
 * complete, the phone syncs so the finished ITP is on it.
 */
#[Layout('layouts.app')]
#[Title('Your ITP')]
class ShowGeneration extends Component
{
    use CallsApi;

    #[Locked]
    public string $generationId;

    /**
     * The API's Generation object; empty until it has been fetched.
     *
     * @var array<string, mixed>
     */
    #[Locked]
    public array $generation = [];

    /**
     * Follow-up answers, keyed by follow-up id.
     *
     * @var array<int|string, string>
     */
    public array $answers = [];

    /**
     * The section (by part) the user is saying doesn't resonate.
     */
    public ?int $flaggingPart = null;

    public string $user_explanation = '';

    public string $requested_change = '';

    /**
     * The line within the flagged section that bothered the user most, if they chose one.
     */
    public ?int $segment_id = null;

    /**
     * The section whose versions the user last chose between, so a refusal shows beside it.
     */
    #[Locked]
    public ?int $choosingPart = null;

    public function mount(string $generationId, GenerationClient $generations, SyncNow $sync): void
    {
        $this->generationId = $generationId;

        $this->fetch($generations, $sync);
    }

    /**
     * Polled while the server is working; also the "try again" after a failed fetch.
     */
    public function refresh(GenerationClient $generations, SyncNow $sync): void
    {
        $this->resetErrorBag('form');

        $this->fetch($generations, $sync);
    }

    public function answerFollowups(GenerationClient $generations): void
    {
        $this->validate(['answers.*' => ['nullable', 'string']]);

        $answers = array_map(function (array $followup): array {
            $text = trim((string) ($this->answers[$followup['id']] ?? ''));

            return ['followup_id' => (int) $followup['id'], 'answer_text' => $text === '' ? null : $text];
        }, $this->generation['followups'] ?? []);

        $this->submitToApi(fn () => $generations->answerFollowups($this->generationId, $answers));
    }

    public function startFlag(int $part): void
    {
        $this->reset('user_explanation', 'requested_change', 'segment_id', 'choosingPart');
        $this->resetErrorBag();

        $this->flaggingPart = $part;
    }

    public function cancelFlag(): void
    {
        $this->reset('flaggingPart', 'user_explanation', 'requested_change', 'segment_id');
        $this->resetErrorBag();
    }

    public function flag(GenerationClient $generations): void
    {
        $this->user_explanation = trim($this->user_explanation);
        $this->requested_change = trim($this->requested_change);

        $section = $this->section($this->flaggingPart);

        $this->validate([
            'flaggingPart' => ['required', 'integer'],
            'user_explanation' => ['required', 'string', 'max:2000'],
            'requested_change' => ['nullable', 'string', 'max:2000'],
            'segment_id' => ['nullable', 'integer', Rule::in(array_column($section['segments'] ?? [], 'id'))],
        ], attributes: ['user_explanation' => 'explanation', 'requested_change' => 'change', 'segment_id' => 'line']);

        $this->submitToApi(fn () => $generations->flagSection(
            $this->generationId,
            $this->flaggingPart,
            $this->user_explanation,
            $this->requested_change !== '' ? $this->requested_change : null,
            $this->segment_id,
        ));
    }

    /**
     * Keep the rewritten version of a section, or the original.
     */
    public function choose(int $part, string $keep, GenerationClient $generations): void
    {
        if (! in_array($keep, ['new', 'original'], true)) {
            return;
        }

        $this->resetErrorBag();
        $this->choosingPart = $part;

        $this->submitToApi(fn () => $generations->chooseSection($this->generationId, $part, $keep));
    }

    public function accept(GenerationClient $generations): void
    {
        $this->submitToApi(fn () => $generations->accept($this->generationId));
    }

    /**
     * A status this app doesn't know yet is taken as a new server stage, so the page keeps polling.
     */
    public function render(): View
    {
        $status = GenerationStatus::tryFrom($this->generation['status'] ?? '');
        $sections = $this->sections();

        return view('livewire.show-generation', [
            'status' => $status,
            'sections' => $sections,
            'isRewriting' => collect($sections)->contains(fn (array $section): bool => $section['regenerating'] ?? false),
            'awaitsChoice' => collect($sections)->contains(fn (array $section): bool => ($section['proposal'] ?? null) !== null),
            'isPolling' => $this->generation !== [] && ($status?->isInProgress() ?? true),
            'itpIsHere' => $this->itpIsHere(),
        ]);
    }

    protected function fetch(GenerationClient $generations, SyncNow $sync): void
    {
        $generation = $this->callApi(fn () => $generations->find($this->generationId));

        if ($generation !== null) {
            $this->generation = $generation;

            $this->syncWhenComplete($sync);
        }
    }

    /**
     * Send an answer to the server, then show the generation it returns.
     *
     * @param  Closure(): array<string, mixed>  $call
     */
    protected function submitToApi(Closure $call): void
    {
        $generation = $this->callApi($call);

        if ($generation !== null) {
            $this->generation = $generation;
            $this->reset('answers', 'flaggingPart', 'user_explanation', 'requested_change', 'segment_id', 'choosingPart');
        }
    }

    /**
     * The attunement's sections, in part order: current lines, and any rewrite in progress or
     * waiting for the user's choice.
     *
     * @return list<array{part: int, segments: list<array{id: int, order: int, text: string}>, regenerating?: bool, proposal?: array{flag_id: int, segments: list<array{id: int, order: int, text: string}>}|null, pinpointed_segment_id?: int|null}>
     */
    protected function sections(): array
    {
        return $this->generation['attunement']['sections'] ?? [];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function section(?int $part): ?array
    {
        return collect($this->sections())->firstWhere('part', $part);
    }

    /**
     * The finished ITP reaches the phone through a normal sync pull. Only sync while it isn't
     * here yet, so revisiting a finished generation doesn't sync every time.
     */
    protected function syncWhenComplete(SyncNow $sync): void
    {
        if (($this->generation['status'] ?? null) === GenerationStatus::Complete->value && ! $this->itpIsHere()) {
            $sync->run();
        }
    }

    protected function itpIsHere(): bool
    {
        $itpId = $this->generation['itp_id'] ?? null;

        return $itpId !== null && Auth::user()->itps()->whereKey($itpId)->exists();
    }
}
