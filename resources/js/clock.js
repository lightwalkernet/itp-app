/*
 * The person's own clock (brief 1 §3 "Hour"). Today and the prompt render for the server's guess,
 * then ask this and send the device's hour, minute and app day with $wire.setClock() when the guess
 * was wrong (the web server runs in UTC). The app day runs 05:00 to 05:00: its date is the local
 * date five hours ago, so a reflection kept at half past midnight belongs to the day before.
 */

const pad = n => String(n).padStart(2, '0')

export function itpClock(now = new Date()) {
    const day = new Date(now.getFullYear(), now.getMonth(), now.getDate(), now.getHours() - 5, now.getMinutes())

    return {
        hour: now.getHours(),
        minute: now.getMinutes(),
        date: `${day.getFullYear()}-${pad(day.getMonth() + 1)}-${pad(day.getDate())}`,
    }
}

// Called from a page root's x-init: <div x-init="itpSyncClock($wire)">
export function itpSyncClock($wire) {
    const clock = itpClock()
    if (clock.hour !== $wire.hour || clock.minute !== $wire.minute || clock.date !== $wire.date) {
        $wire.setClock(clock.hour, clock.date, clock.minute)
    }
}

window.itpClock = itpClock
window.itpSyncClock = itpSyncClock
