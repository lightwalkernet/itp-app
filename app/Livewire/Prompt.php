<?php

namespace App\Livewire;

use App\Livewire\Concerns\KnowsTheDeviceClock;
use App\Support\AppDay;
use App\Support\DayEntries;
use App\Support\LivingWith;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Itp\Core\Enums\EntryKind;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The prompt (brief 1 §3.1): one question from Today, answered in writing, kept in the record.
 * A light room with the bar away. Keeping replaces the day's entry of that kind, so asking twice
 * never makes two. Speaking arrives in brief 3.
 */
#[Layout('layouts.app')]
class Prompt extends Component
{
    use KnowsTheDeviceClock;

    /**
     * The kicker, question and hint for each kind.
     *
     * @var array<string, array{kicker: string, question: string, hint: string}>
     */
    protected const COPY = [
        'morning' => ['kicker' => 'Morning practice', 'question' => 'What does my ITP Self want today?', 'hint' => 'A sentence is enough.'],
        'step' => ['kicker' => 'One aligned step', 'question' => 'What is one step toward it today?', 'hint' => 'Small counts: the email, the question, the rest.'],
        'evening' => ['kicker' => 'Evening reflection', 'question' => 'What shifted today?', 'hint' => 'Movement, not achievement.'],
        'evidence' => ['kicker' => 'Celebrate the evidence', 'question' => 'What are you proud of today?', 'hint' => 'Glimmers, openings, courage — anything.'],
    ];

    #[Locked]
    public string $kind;

    public string $body = '';

    /**
     * The words already kept today, put in the box so a second visit edits rather than repeats.
     */
    #[Locked]
    public string $kept = '';

    public function mount(string $kind): void
    {
        $this->kind = EntryKind::from($kind)->value;

        $this->guessClock();

        $this->fillFromToday();
    }

    /**
     * The device's day can differ from the server's guess: show that day's words instead, unless
     * the person has started typing.
     */
    protected function clockChanged(string $previousDate): void
    {
        if ($previousDate !== $this->date && $this->body === $this->kept) {
            $this->fillFromToday();
        }
    }

    public function keep(): void
    {
        $this->body = trim($this->body);

        $this->validate(
            ['body' => ['required', 'string', 'max:5000']],
            ['body.required' => 'A few words first. A sentence is enough.', 'body.max' => 'That’s a lot to keep at once. Try it shorter.'],
        );

        $user = Auth::user();

        DayEntries::keep($user, $this->date, EntryKind::from($this->kind), $this->body, LivingWith::for($user));

        $this->dispatch('today-navigate', url: route('home', absolute: false), direction: 'back');
    }

    public function render(): View
    {
        $copy = self::COPY[$this->kind];
        $why = $this->kind === EntryKind::Evening->value
            ? AppDay::why(LivingWith::for(Auth::user())?->quantum_whys, $this->date)
            : null;

        return view('livewire.prompt', [
            ...$copy,
            'why' => $why,
        ])->title($copy['kicker']);
    }

    protected function fillFromToday(): void
    {
        $body = DayEntries::for(Auth::user(), $this->date)->get($this->kind)?->body;

        $this->kept = $body === null || $body === DayEntries::NOT_TONIGHT ? '' : $body;
        $this->body = $this->kept;
    }
}
