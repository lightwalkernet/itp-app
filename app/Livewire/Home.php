<?php

namespace App\Livewire;

use App\Api\Exceptions\ApiUnavailable;
use App\Api\GenerationClient;
use App\Api\GenerationStatus;
use App\Livewire\Concerns\CallsApi;
use App\Livewire\Concerns\KnowsTheDeviceClock;
use App\Media\MediaDownloads;
use App\Support\AppDay;
use App\Support\DayEntries;
use App\Support\LivingWith;
use App\Support\Runtime;
use App\Sync\GuestContentPull;
use App\Sync\SyncNow;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Itp\Core\Enums\EntryKind;
use Itp\Core\Models\Entry;
use Itp\Core\Models\Itp;
use Itp\Core\Models\UniversalItp;
use Itp\Core\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Today (brief 1 §3): the threshold a person comes back to all day, and the app's start URL, so
 * guests land here too. A light room: what they are living with, the arc
 * of the day, and exactly one card, which follows the device's hour and what they've kept today.
 * Under it, a way back into a generation still running or waiting on them.
 *
 * Rows are local on the phone and the shared database on the web. Syncing (or, signed out, the
 * public content pull) and asking the API happen after the page shows (`wire:init`); when the API
 * can't be reached the page simply shows what it already has. On the phone, signed in, missing
 * universal audio then downloads one file per poll.
 */
#[Layout('layouts.app')]
#[Title('Today')]
class Home extends Component
{
    use CallsApi;
    use KnowsTheDeviceClock;

    /**
     * The newest generation still running or waiting on the user, if any.
     *
     * @var array{id: string, status: string}|null
     */
    #[Locked]
    public ?array $openGeneration = null;

    public function mount(): void
    {
        $this->guessClock();
    }

    public function refresh(SyncNow $sync, GuestContentPull $content, MediaDownloads $downloads, GenerationClient $generations): void
    {
        if (Auth::guest()) {
            $content->run();
            $downloads->prune();

            return;
        }

        $sync->run(forceGlobal: true);
        $downloads->prune();
        $downloads->retryFailed();

        $this->callApi(function () use ($generations): void {
            try {
                $list = $generations->index();
            } catch (ApiUnavailable) {
                return;
            }

            $open = collect($list)->first(function (array $generation): bool {
                $status = GenerationStatus::tryFrom($generation['status'] ?? '');

                return $status !== null && ($status->isInProgress() || $status->isWaitingOnUser());
            });

            $this->openGeneration = $open === null ? null : ['id' => $open['id'], 'status' => $open['status']];
        });
    }

    /**
     * Fetch the next missing audio file. Polled while any are pending, so each request stays
     * short. Guests download nothing: the previews' audio is bundled.
     */
    public function downloadNext(MediaDownloads $downloads): void
    {
        if (Auth::check()) {
            $downloads->downloadNext();
        }
    }

    /**
     * Enter what they're living with: its page, for now. Visiting it from here counts as entering
     * today, which moves the morning card on to its question.
     */
    public function enter(): void
    {
        $itp = LivingWith::for(Auth::user());

        if ($itp === null) {
            return;
        }

        // brief 2: replace the flag with the player's play record, and go to the player.
        session()->put($this->enteredKey(), true);

        $this->dispatch('today-navigate', url: $this->pageOf($itp), direction: 'forward');
    }

    /**
     * "Not tonight — that's allowed": an evening kept with no words, so the day can close.
     */
    public function notTonight(): void
    {
        $user = Auth::user();

        if ($user === null) {
            return;
        }

        DayEntries::keep($user, $this->date, EntryKind::Evening, DayEntries::NOT_TONIGHT, LivingWith::for($user));
    }

    public function render(MediaDownloads $downloads): View
    {
        $user = Auth::user()?->load('settings');
        $itp = LivingWith::for($user);
        $entries = $user === null ? collect() : DayEntries::for($user, $this->date);

        return view('livewire.home', [
            'user' => $user,
            'itpName' => $itp === null ? 'Meet Your ITP Self' : $this->nameOf($itp),
            'itpUrl' => $itp === null ? null : $this->pageOf($itp),
            'card' => $this->card($user, $itp, $entries),
            'arc' => $this->arc($entries),
            'returnUrl' => $this->returnToCoherenceUrl(),
            'links' => $this->links(),
            'openStatus' => GenerationStatus::tryFrom($this->openGeneration['status'] ?? ''),
            'downloadsPending' => Runtime::isMobile() && $user !== null && $downloads->hasPending(),
        ]);
    }

    /**
     * The one card (brief 1 §3's table), as the view draws it.
     *
     * @param  Collection<string, Entry>  $entries
     * @return array{state: string, kicker: string, title: string, quote: bool, line: string}
     */
    protected function card(?User $user, UniversalItp|Itp|null $itp, Collection $entries): array
    {
        $name = $itp === null ? 'Meet Your ITP Self' : $this->nameOf($itp);
        $quoted = fn (?Entry $entry): string => '“'.$entry?->body.'”';

        if ($user === null) {
            return $this->state('guest', 'This morning', $name, 'Four Universals are open before you sign in. Your own ITP comes after.');
        }

        $part = AppDay::part($this->hour);
        $morning = $entries->get(EntryKind::Morning->value);
        $evening = $entries->get(EntryKind::Evening->value);
        $evidence = $entries->get(EntryKind::Evidence->value);
        $why = AppDay::why($itp?->quantum_whys, $this->date);

        if ($part === 'morning') {
            if ($morning !== null) {
                return $this->state('morning-kept', 'This morning', $quoted($morning), 'Carry it lightly. The day will meet you.', quote: true);
            }

            if (session()->get($this->enteredKey()) === true) {
                return $this->state('morning-ask', 'In its afterglow', 'What does my ITP Self want today?', 'Speak it or write it. A sentence is enough.');
            }

            return $this->state('morning-enter', 'This morning', $name, 'Enter it first. Three minutes with the light, then one question.');
        }

        if ($part === 'afternoon') {
            if ($why !== null && $this->minutesIntoDay() >= $this->whyAt($user)) {
                return $this->state('afternoon-why', 'This afternoon, a Quantum Why', '“'.$why.'”', 'Nothing to answer. Just carry it, and see what the day does with it.', quote: true);
            }

            return $this->state('afternoon-return', 'Whenever you need it', 'Return to alignment', $morning !== null
                ? 'This morning: '.$quoted($morning)
                : 'The day pulls. Here is the way back to centre.');
        }

        if ($evening === null) {
            return $this->state('evening-ask', $part === 'night' ? 'Before sleep' : 'Evening reflection', 'What shifted today?', $why !== null
                ? 'This afternoon it asked: “'.$why.'” Did you notice anything?'
                : 'Did you notice anything?');
        }

        if ($evidence !== null) {
            return $this->state('complete', 'Today is complete', $quoted($evidence), 'Kept in your record.', quote: true);
        }

        if ($part === 'evening' && $evening->body !== DayEntries::NOT_TONIGHT) {
            return $this->state('evening-evidence', 'Celebrate the evidence', 'What are you proud of today?', 'Glimmers, synchronicities, courage. It all counts.');
        }

        return $this->state('night-done', 'Before sleep', $name, 'Let the day go quiet before it closes.');
    }

    /**
     * @return array{state: string, kicker: string, title: string, quote: bool, line: string}
     */
    protected function state(string $state, string $kicker, string $title, string $line, bool $quote = false): array
    {
        return ['state' => $state, 'kicker' => $kicker, 'title' => $title, 'quote' => $quote, 'line' => $line];
    }

    /**
     * Where the sun sits on the arc (5 am at the left, 9 pm at the right, hidden at night) and
     * which of the day's two points are filled. Ported from docs/design/assembly.html.
     *
     * @param  Collection<string, Entry>  $entries
     * @return array{sunX: string, sunY: string, sunVisible: bool, morningDone: bool, eveningDone: bool}
     */
    protected function arc(Collection $entries): array
    {
        $t = min(1, max(0, ($this->hour + $this->minute / 60 - 5) / 16));
        $evening = $entries->get(EntryKind::Evening->value);

        return [
            'sunX' => number_format(12 + $t * 376, 1, '.', ''),
            'sunY' => number_format((1 - $t) ** 2 * 54 + 2 * (1 - $t) * $t * -20 + $t ** 2 * 54, 1, '.', ''),
            'sunVisible' => $this->hour >= 5 && $this->hour <= 20,
            'morningDone' => $entries->has(EntryKind::Morning->value),
            'eveningDone' => $evening !== null && $evening->body !== DayEntries::NOT_TONIGHT,
        ];
    }

    /**
     * When the day's Why arrives, in minutes since midnight. A user without a settings row yet
     * gets the default.
     */
    protected function whyAt(User $user): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $user->settings?->why_at ?? '14:30:00'));

        return $hour * 60 + $minute;
    }

    protected function enteredKey(): string
    {
        return 'entered:'.$this->date;
    }

    protected function nameOf(UniversalItp|Itp $itp): string
    {
        return $itp instanceof UniversalItp ? $itp->display_name : $itp->title;
    }

    protected function pageOf(UniversalItp|Itp $itp): string
    {
        return $itp instanceof UniversalItp
            ? route('universals.show', $itp, absolute: false)
            : route('itps.show', $itp, absolute: false);
    }

    /**
     * Return to Coherence's page, or null while the primer isn't in the content (coming soon).
     */
    protected function returnToCoherenceUrl(): ?string
    {
        $primer = UniversalItp::query()->where('slug', 'return-to-coherence')->first(['id', 'slug']);

        return $primer === null ? null : route('universals.show', $primer, absolute: false);
    }

    /**
     * Paths for the card's other links, falling back to the path while a room is still being built.
     *
     * @return array{itps: string, record: string, signIn: string}
     */
    protected function links(): array
    {
        return [
            'itps' => Route::has('itps.index') ? route('itps.index', absolute: false) : '/itps',
            'record' => Route::has('you') ? route('you', absolute: false) : '/you',
            'signIn' => route('sign-in', absolute: false),
        ];
    }
}
