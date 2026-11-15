<?php

namespace App\Livewire;

use App\Support\LivingWith;
use App\Support\Runtime;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Itp\Core\Models\Itp;
use Itp\Core\Models\UserSetting;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * You (brief 1 §6): the record, what you're living with, the day's three reminders, the field,
 * about, and the account. Reminder preferences are stored on the user's settings row through
 * the model, so they sync; the notifications themselves arrive in brief 5. Still mode is
 * device-only and lives in the browser (field.js).
 */
#[Layout('layouts.app')]
#[Title('You')]
class You extends Component
{
    /**
     * Each reminder: its switch column and its time column.
     *
     * @var array<string, array{switch: string, time: string}>
     */
    protected const array REMINDERS = [
        'morning' => ['switch' => 'morning_reminder', 'time' => 'morning_at'],
        'why' => ['switch' => 'why_reminder', 'time' => 'why_at'],
        'evening' => ['switch' => 'evening_reminder', 'time' => 'evening_at'],
    ];

    /**
     * Each reminder's time, `H:i`, as the time inputs read and write it.
     *
     * @var array<string, string>
     */
    public array $times = [];

    /**
     * @var array<string, bool>
     */
    public array $reminders = [];

    public function mount(): void
    {
        $settings = Auth::user()->settings ?? new UserSetting;

        foreach (self::REMINDERS as $reminder => $columns) {
            $this->times[$reminder] = substr((string) $settings->{$columns['time']}, 0, 5);
            $this->reminders[$reminder] = (bool) $settings->{$columns['switch']};
        }
    }

    public function toggleReminder(string $reminder): void
    {
        abort_unless(array_key_exists($reminder, self::REMINDERS), 404);

        $this->reminders[$reminder] = ! $this->reminders[$reminder];

        $this->settings()->update([self::REMINDERS[$reminder]['switch'] => $this->reminders[$reminder]]);
    }

    /**
     * A time input changed: keep it if it is a time of day.
     */
    public function updatedTimes(mixed $value, string $reminder): void
    {
        abort_unless(array_key_exists($reminder, self::REMINDERS), 404);

        $this->validate(
            ["times.{$reminder}" => ['required', 'date_format:H:i']],
            attributes: ["times.{$reminder}" => 'time'],
        );

        $this->settings()->update([self::REMINDERS[$reminder]['time'] => $this->times[$reminder]]);
    }

    public function render(): View
    {
        $user = Auth::user();
        $livingWith = LivingWith::for($user);

        return view('livewire.you', [
            'user' => $user,
            'livingWithName' => $livingWith instanceof Itp ? $livingWith->title : $livingWith?->display_name,
            'livingWithUrl' => match (true) {
                $livingWith instanceof Itp => route('itps.show', $livingWith, absolute: false),
                $livingWith !== null => route('universals.show', $livingWith, absolute: false),
                default => null,
            },
            'deleteAccountUrl' => Runtime::isMobile()
                ? rtrim((string) config('itp.web_url'), '/').'/delete-account'
                : route('account.delete', absolute: false),
        ]);
    }

    /**
     * The user's settings row. It comes from sign-in; it is created here only if it somehow
     * isn't there yet.
     */
    protected function settings(): UserSetting
    {
        return Auth::user()->settings()->firstOrCreate();
    }
}
