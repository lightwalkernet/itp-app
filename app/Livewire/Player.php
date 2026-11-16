<?php

namespace App\Livewire;

use App\Livewire\Concerns\KnowsTheDeviceClock;
use App\Media\MediaLibrary;
use App\Support\Welcome;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Itp\Core\Models\Itp;
use Itp\Core\Models\UniversalItp;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;
use Livewire\Component;

/**
 * The player (player contract), ported from itpmobile's Player components: an ITP's attunement
 * (and the Introduction) as words in step with its voice, its Breathe 11 with the breath pacer,
 * or one of its Quantum Whys. A light room with the bar away.
 *
 * The server only says what to play: the timed sentences, the audio URLs from MediaLibrary and
 * the Whys. resources/js/player.js runs it all in the browser off `audio.currentTime`. When the
 * audio can't play here yet, the page says why and plays nothing.
 */
#[Layout('layouts.app')]
class Player extends Component
{
    use KnowsTheDeviceClock;

    /**
     * The five base sounds of the breath pacer, bundled in public/.
     *
     * @var list<string>
     */
    public const array BREATH_SOUNDS = ['breathin', 'breathout', 'in', 'out', 'soitis'];

    /**
     * `universal` (key = slug) or `itp` (key = the personal ITP's id).
     */
    #[Locked]
    public string $source;

    #[Locked]
    public string $key;

    /**
     * `itp` (the attunement), `breathe` or `why`.
     */
    #[Locked]
    public string $piece;

    /**
     * Where the person came from: `today`, `welcome` (the Introduction on first launch) or null
     * (an ITP's page).
     */
    #[Locked]
    public ?string $from = null;

    /**
     * Where finishing goes.
     */
    #[Locked]
    public string $finishUrl = '';

    public function mount(MediaLibrary $media, string $source, string $key, string $piece): void
    {
        $this->source = $source;
        $this->key = $key;
        $this->piece = $piece;

        $itp = $this->itp();

        if ($itp instanceof UniversalItp && Auth::guest() && ! $this->isOpenToGuests($itp, $media)) {
            $this->redirectRoute('sign-in');

            return;
        }

        $from = request()->query('from');
        $this->from = match (true) {
            $from === 'today' => 'today',
            $from === 'welcome' && $this->isTheIntroduction($itp) => 'welcome',
            default => null,
        };

        $this->finishUrl = $this->from === null ? $this->pageOf($itp) : route('home', absolute: false);

        if ($this->marksTheDay()) {
            $this->guessClock();
        }
    }

    /**
     * Playback started: entering from Today counts as entering the day, which moves the morning
     * card on to its question (Home reads `entered:{app day}`).
     */
    #[Renderless]
    public function markEntered(): void
    {
        if ($this->marksTheDay()) {
            session()->put('entered:'.$this->date, true);
        }
    }

    /**
     * Finished, stopped or skipped: back where they came from. The Introduction from the welcome
     * marks this device welcomed.
     */
    #[Renderless]
    public function finish(): void
    {
        if ($this->from === 'welcome') {
            Welcome::markSeen();
        }

        $this->dispatch('player-navigate', url: $this->finishUrl, direction: $this->from === 'welcome' ? 'replace' : 'back');
    }

    /**
     * The device clock never changes what the player shows, so don't re-render under the words.
     */
    protected function clockChanged(string $previousDate): void
    {
        $this->skipRender();
    }

    public function render(MediaLibrary $media): View
    {
        $itp = $this->itp();

        return view('livewire.player', [
            'itp' => $itp,
            'name' => $itp instanceof UniversalItp ? $itp->display_name : $itp->title,
            'storageKey' => $this->source.':'.$itp->getKey(),
            ...match ($this->piece) {
                'itp' => $this->words($itp, $media),
                'breathe' => $this->breathe($itp, $media),
                'why' => $this->why($itp),
            },
        ])->title(match ($this->piece) {
            'breathe' => 'Breathe 11',
            'why' => 'A Quantum Why',
            default => $itp instanceof UniversalItp ? $itp->display_name : $itp->title,
        });
    }

    /**
     * The attunement (or the Introduction): its sentences and its voice.
     *
     * @return array<string, mixed>
     */
    protected function words(UniversalItp|Itp $itp, MediaLibrary $media): array
    {
        $sentences = $this->sentences($itp->attunement_timings);

        return [
            'sentences' => $sentences,
            'audioUrl' => $media->url($itp->attunement_audio_path),
            'unavailable' => $this->unavailable($itp->attunement_audio_path, $sentences, $media),
        ];
    }

    /**
     * Breathe 11: its eleven lines, its voice and the pacer's sounds.
     *
     * @return array<string, mixed>
     */
    protected function breathe(UniversalItp|Itp $itp, MediaLibrary $media): array
    {
        $sentences = $this->sentences($itp->breath11_timings);

        return [
            'sentences' => $sentences,
            'audioUrl' => $media->url($itp->breath11_audio_path),
            'sounds' => collect(self::BREATH_SOUNDS)
                ->mapWithKeys(fn (string $sound): array => [$sound => asset("media/audio/base/{$sound}.mp3")])
                ->all(),
            'unavailable' => $this->unavailable($itp->breath11_audio_path, $sentences, $media),
        ];
    }

    /**
     * A Quantum Why needs no audio: the browser picks one this device hasn't shown yet.
     *
     * @return array<string, mixed>
     */
    protected function why(UniversalItp|Itp $itp): array
    {
        $whys = array_values(array_filter(
            array_map(fn (mixed $why): string => is_string($why) ? trim($why) : '', $this->decoded($itp->quantum_whys)),
            fn (string $why): bool => $why !== '',
        ));

        return [
            'whys' => $whys,
            'unavailable' => $whys === [] ? 'This one doesn’t have its Quantum Whys yet.' : null,
        ];
    }

    /**
     * Why this piece can't play here, or null when it can.
     *
     * @param  list<array<string, mixed>>  $sentences
     */
    protected function unavailable(?string $audioKey, array $sentences, MediaLibrary $media): ?string
    {
        if ($audioKey === null || $sentences === []) {
            return 'This one doesn’t have its voice yet.';
        }

        return $media->url($audioKey) === null ? 'Still downloading — try again in a moment.' : null;
    }

    /**
     * Timings as the player reads them, `[{start, end, text, words: [{word, start, end}]}]`, in
     * order. Anything without a start and an end is left out; a sentence without word times
     * arrives as one word.
     *
     * @return list<array{start: float, end: float, text: string, words: list<array{word: string, start: float, end: float}>}>
     */
    protected function sentences(mixed $timings): array
    {
        $sentences = [];

        foreach ($this->decoded($timings) as $sentence) {
            if (! is_array($sentence) || ! is_numeric($sentence['start'] ?? null) || ! is_numeric($sentence['end'] ?? null)) {
                continue;
            }

            $words = [];

            foreach ((array) ($sentence['words'] ?? []) as $word) {
                if (is_array($word) && is_string($word['word'] ?? null) && is_numeric($word['start'] ?? null)) {
                    $words[] = [
                        'word' => $word['word'],
                        'start' => (float) $word['start'],
                        'end' => (float) ($word['end'] ?? $word['start']),
                    ];
                }
            }

            $text = is_string($sentence['text'] ?? null) ? $sentence['text'] : implode(' ', array_column($words, 'word'));

            if ($words === [] && trim($text) === '') {
                continue;
            }

            $sentences[] = [
                'start' => (float) $sentence['start'],
                'end' => (float) $sentence['end'],
                'text' => $text,
                'words' => $words !== [] ? $words : [['word' => $text, 'start' => (float) $sentence['start'], 'end' => (float) $sentence['end']]],
            ];
        }

        usort($sentences, fn (array $a, array $b): int => $a['start'] <=> $b['start']);

        return $sentences;
    }

    /**
     * A JSON column as an array, also when the export left it as JSON text.
     *
     * @return array<mixed>
     */
    protected function decoded(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return is_array($value) ? $value : [];
    }

    /**
     * The ITP being played. A personal ITP is its owner's only: anyone else gets a 404.
     */
    protected function itp(): UniversalItp|Itp
    {
        if ($this->source === 'universal') {
            return UniversalItp::query()->where('slug', $this->key)->firstOrFail();
        }

        $itp = Itp::query()->findOrFail($this->key);

        abort_unless(Auth::check() && $itp->user_id === Auth::id(), 404);

        return $itp;
    }

    /**
     * Guests play the previews, and the Introduction, which the welcome plays. A guest downloads
     * nothing, so on the phone a preview whose voice the app doesn't bundle waits for sign-in.
     */
    protected function isOpenToGuests(UniversalItp $itp, MediaLibrary $media): bool
    {
        if (! $itp->is_preview && $itp->slug !== Welcome::INTRODUCTION_SLUG) {
            return false;
        }

        $audioKey = match ($this->piece) {
            'itp' => $itp->attunement_audio_path,
            'breathe' => $itp->breath11_audio_path,
            default => null,
        };

        return $audioKey === null || $media->url($audioKey) !== null;
    }

    protected function isTheIntroduction(UniversalItp|Itp $itp): bool
    {
        return $itp instanceof UniversalItp && $itp->slug === Welcome::INTRODUCTION_SLUG && $this->piece === 'itp';
    }

    /**
     * Only the attunement entered from Today marks the day.
     */
    protected function marksTheDay(): bool
    {
        return $this->from === 'today' && $this->piece === 'itp';
    }

    protected function pageOf(UniversalItp|Itp $itp): string
    {
        return $itp instanceof UniversalItp
            ? route('universals.show', $itp, absolute: false)
            : route('itps.show', $itp, absolute: false);
    }
}
