<div data-room="white">
    <div class="col col--room">
        @if ($deleted)
            <x-room kicker="Done" title="Your account is deleted." />
            <p class="t-body t-body--lg delete-copy">Everything that was in it is gone.</p>
            {{-- A full page load, so the bar is rebuilt signed out. --}}
            <a href="{{ route('home', absolute: false) }}" class="btn">Back to Today</a>
        @else
            @auth
                <a href="{{ route('you', absolute: false) }}" data-transition="back" class="back"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg> You</a>
            @endauth

            <x-room kicker="Your account" title="Delete your account" />

            <p class="t-body t-body--lg delete-copy">This deletes your account and everything in it: your personal ITPs, your record of what you’ve kept, and your settings. It can’t be undone.</p>

            @if (! $codeSent)
                <form wire:submit="sendCode">
                    <p class="t-body t-soft delete-copy">First, a six-digit code to your email, to make sure it’s you.</p>
                    <label for="email" class="sr-only">Email</label>
                    <input wire:model="email" id="email" type="email" class="field-input" placeholder="you@…" autocomplete="email" inputmode="email" autocapitalize="off" required>
                    @error('email') <p class="field-error">{{ $message }}</p> @enderror

                    <x-button type="submit" wire:loading.attr="disabled">Send a code</x-button>
                </form>
            @else
                <form wire:submit="deleteAccount">
                    <p class="t-body t-soft delete-copy">We sent a six-digit code to {{ $email }}.</p>
                    <label for="code" class="sr-only">Code</label>
                    <input wire:model="code" id="code" class="field-input field-input--code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="six digits" required>
                    @error('code') <p class="field-error">{{ $message }}</p> @enderror

                    <x-button type="submit" wire:loading.attr="disabled">Delete my account</x-button>
                    <button type="button" class="btn-sec" wire:click="useDifferentEmail">Use a different email</button>
                </form>
            @endif
        @endif
    </div>
</div>
