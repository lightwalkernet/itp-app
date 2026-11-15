@php
    $reminderRows = [
        'morning' => ['label' => 'Morning', 'meta' => 'enter, then one question', 'aria' => 'Morning reminder'],
        'why' => ['label' => 'A Quantum Why', 'meta' => 'one a day, from your ITP', 'aria' => 'Quantum Why'],
        'evening' => ['label' => 'Evening', 'meta' => 'what shifted today?', 'aria' => 'Evening reminder'],
    ];
@endphp
<div data-room="white">
    <div class="col col--room">
        <x-room :kicker="$user->first_name" title="You" />

        <div class="rows you-rows">
            {{-- brief 4: the record. Until then this leads to Your ITPs. --}}
            <x-row href="{{ route('itps.index', absolute: false) }}" meta="what you’ve kept">Your record</x-row>
            @if ($livingWithUrl !== null)
                <x-row href="{{ $livingWithUrl }}" :meta="$livingWithName.' · change'">Living with</x-row>
            @else
                <x-row meta="coming soon" aria-disabled="true">Living with</x-row>
            @endif
        </div>

        <h3 class="section-h">The day’s three reminders</h3>
        @foreach ($reminderRows as $reminder => $row)
            <div class="srow" wire:key="reminder-{{ $reminder }}">
                <label for="reminder-time-{{ $reminder }}">{{ $row['label'] }}<span class="srow__meta">{{ $row['meta'] }}</span></label>
                <span class="srow__side">
                    <input type="time" id="reminder-time-{{ $reminder }}" class="srow__value srow__time" value="{{ $times[$reminder] }}" wire:model.change.live="times.{{ $reminder }}" required>
                    <button type="button" class="switch" role="switch" aria-checked="{{ $reminders[$reminder] ? 'true' : 'false' }}" aria-label="{{ $row['aria'] }}" wire:click="toggleReminder('{{ $reminder }}')"></button>
                </span>
            </div>
            @error("times.{$reminder}") <p class="field-error">{{ $message }}</p> @enderror
        @endforeach

        <h3 class="section-h">The field</h3>
        {{-- Device-only, like the light itself: field.js keeps it in localStorage. --}}
        <div class="srow" x-data="{ still: false }" x-init="try { still = localStorage.getItem('itp.still') === '1' } catch (e) {}">
            <span>Still mode<span class="srow__meta">the light rests; nothing moves</span></span>
            <button type="button" class="switch" role="switch" aria-checked="false" :aria-checked="still ? 'true' : 'false'" aria-label="Still mode" x-on:click="still = !still; window.itpField.setStill(still)"></button>
        </div>
        <div class="srow">
            <span>Text size<span class="srow__meta">follows your phone</span></span>
            <span class="srow__value">on</span>
        </div>

        <h3 class="section-h">About</h3>
        <div class="rows you-rows--flush">
            <x-row href="{{ route('about', absolute: false) }}">About It’s Totally Possible</x-row>
        </div>

        <h3 class="section-h">Account</h3>
        <div class="srow">
            <span>Signed in<span class="srow__meta">{{ $user->email }}</span></span>
            <form method="POST" action="{{ route('sign-out', absolute: false) }}">
                @csrf
                <button type="submit" class="btn-sec you-sign-out">Sign out</button>
            </form>
        </div>
        @if (\App\Support\Runtime::isMobile())
            {{-- Not a page in the app: the phone opens the web build's deletion page in the browser (platform.md §5). --}}
            <a href="{{ $deleteAccountUrl }}" class="btn-sec you-delete">Delete account</a>
        @else
            <a href="{{ $deleteAccountUrl }}" data-transition="forward" class="btn-sec you-delete">Delete account</a>
        @endif
    </div>
</div>
