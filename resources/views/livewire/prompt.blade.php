{{--
    The prompt (brief 1 §3.1): one question in the light, the answer on white. The day comes from
    the device (itpSyncClock, resources/js/clock.js). Keep dispatches today-navigate back to Today.
--}}
<div data-room="light" data-bar="hidden" class="page-center prompt"
     x-data
     x-init="itpSyncClock($wire)"
     x-on:today-navigate.window="window.pageTransitions ? window.pageTransitions[$event.detail.direction]($event.detail.url) : Livewire.navigate($event.detail.url)">
    <header class="col">
        <a class="back" href="{{ route('home', absolute: false) }}" data-transition="back"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg> back</a>
    </header>

    <form class="col prompt__body" wire:submit="keep">
        <p class="t-kicker lit-soft">{{ $kicker }}</p>
        <h1 class="t-lit lit">{{ $question }}</h1>
        <p class="t-hint lit-soft">{{ $hint }}</p>
        @if ($why !== null)
            <p class="t-hint lit-soft">This afternoon it asked: “{{ $why }}”</p>
        @endif

        <div class="answer">
            <label for="prompt-answer" class="sr-only">{{ $question }}</label>
            <textarea id="prompt-answer" class="field-input" rows="3" wire:model="body" placeholder="{{ $hint }}"></textarea>
        </div>
        @error('body')
            <p class="t-hint lit-soft" role="alert">{{ $message }}</p>
        @enderror

        <div class="modes">
            {{-- brief 3: speaking an answer --}}
            <button type="button" class="mode" disabled>
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg>
                Speak <small>soon</small>
            </button>
            <button type="submit" class="mode" wire:loading.attr="disabled" wire:target="keep">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12l5 5L20 7"/></svg>
                Keep
            </button>
        </div>
    </form>

    <div></div>
</div>
