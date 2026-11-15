<?php

namespace App\Livewire\Concerns;

use App\Support\AppDay;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Livewire\Attributes\Locked;

/**
 * The person's own hour and app day (brief 1 §3 "Hour"). The page first renders for the server's
 * guess (the request time in the app's timezone); then `itpClock()` in the browser
 * (resources/js/clock.js) reads the device clock and calls `setClock()` if the guess was wrong.
 * The web server runs in UTC, so the card must never trust its own date.
 */
trait KnowsTheDeviceClock
{
    #[Locked]
    public int $hour = 0;

    #[Locked]
    public int $minute = 0;

    /**
     * The app day, Y-m-d: the device's date five hours ago.
     */
    #[Locked]
    public string $date = '';

    /**
     * The server's guess, from the request time in the device's time zone when the browser has said
     * which (the `itp_tz` cookie, set in the page head), so the first render is already right. Call
     * from the component's mount().
     */
    protected function guessClock(): void
    {
        $now = CarbonImmutable::now($this->deviceTimezone());

        $this->hour = $now->hour;
        $this->minute = $now->minute;
        $this->date = AppDay::date($now);
    }

    /**
     * The time zone the device reported, or the app's when it hasn't (yet) or names no real zone.
     */
    protected function deviceTimezone(): string
    {
        $timezone = request()->cookie('itp_tz');

        return is_string($timezone) && in_array($timezone, DateTimeZone::listIdentifiers(), true)
            ? $timezone
            : config('app.timezone');
    }

    /**
     * Take the device's hour, minute and app day. Anything that isn't a real time, or a date more
     * than two days from the server's, is ignored: no time zone is that far away.
     */
    public function setClock(mixed $hour, mixed $date, mixed $minute = 0): void
    {
        if (! is_int($hour) || $hour < 0 || $hour > 23 || ! is_int($minute) || $minute < 0 || $minute > 59) {
            return;
        }

        if (! is_string($date) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts) || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            return;
        }

        if (abs(CarbonImmutable::parse($date)->diffInDays(CarbonImmutable::now()->startOfDay())) > 2) {
            return;
        }

        $previousDate = $this->date;

        $this->hour = $hour;
        $this->minute = $minute;
        $this->date = $date;

        $this->clockChanged($previousDate);
    }

    /**
     * Called after the device clock arrives, with the app day the page had before.
     */
    protected function clockChanged(string $previousDate): void
    {
        //
    }

    /**
     * Minutes since midnight on the device.
     */
    protected function minutesIntoDay(): int
    {
        return $this->hour * 60 + $this->minute;
    }
}
