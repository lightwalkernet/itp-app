{{--
    Today (brief 1 §3). A light room. The card renders for the server's guess at the hour, then
    itpSyncClock() (resources/js/clock.js) sends the device's clock if the guess was wrong.
    Actions that move on (Enter, Keep) dispatch today-navigate, so the page slides like a link.
--}}
@php
    $soon = $itpUrl === null;
    $prompt = fn (string $kind): string => route('today.prompt', $kind, absolute: false);
@endphp
<div data-room="light" class="page-center today"
     wire:init="refresh"
     x-data
     x-init="itpSyncClock($wire)"
     x-on:today-navigate.window="window.pageTransitions ? window.pageTransitions[$event.detail.direction]($event.detail.url) : Livewire.navigate($event.detail.url)">
    <header class="col">
        <p class="living">
            <span>Living with</span>
            @if ($soon)
                <span class="living__name">{{ $itpName }}</span>
            @else
                <a class="living__name" href="{{ $itpUrl }}" data-transition="forward">{{ $itpName }}</a>
            @endif
        </p>
        <svg class="arc" viewBox="0 0 400 64" role="img" aria-label="The day’s rhythm">
            <path class="arc__path" d="M 12 54 Q 200 -20 388 54"/>
            <circle @class(['arc__sun', 'arc__sun--hidden' => ! $arc['sunVisible']]) r="5" cx="{{ $arc['sunX'] }}" cy="{{ $arc['sunY'] }}"/>
            <g @class(['arc__pt', 'arc__pt--done' => $arc['morningDone']]) data-point="morning"><circle class="arc__ring" cx="78" cy="34.7" r="4.5"/><circle class="arc__core" cx="78" cy="34.7" r="3"/><text x="78" y="56" text-anchor="middle">morning</text></g>
            <g @class(['arc__pt', 'arc__pt--done' => $arc['eveningDone']]) data-point="evening"><circle class="arc__ring" cx="322" cy="34.7" r="4.5"/><circle class="arc__core" cx="322" cy="34.7" r="3"/><text x="322" y="56" text-anchor="middle">evening</text></g>
        </svg>
    </header>

    <div class="col today__cards">
        <x-card :kicker="$card['kicker']" :title="$card['title']" :quote="$card['quote']" data-card="{{ $card['state'] }}">
            <p @class(['t-body', 't-soft' => $card['quote']])>{{ $card['line'] }}</p>

            @switch($card['state'])
                @case('guest')
                    @if ($soon)
                        <x-button disabled meta="coming soon">Enter</x-button>
                    @else
                        <x-button :href="$itpUrl">Enter</x-button>
                    @endif
                    <a class="btn-sec" href="{{ $links['signIn'] }}" data-transition="forward">Sign in — <em>email and a code</em></a>
                    @break

                @case('morning-enter')
                    {{-- brief 2: Enter goes to the player, and the play record replaces the session flag. --}}
                    @if ($soon)
                        <x-button disabled meta="coming soon">Enter</x-button>
                    @else
                        <x-button wire:click="enter">Enter</x-button>
                    @endif
                    <a class="btn-sec" href="{{ $links['itps'] }}" data-transition="forward">Something else today? <em>Your ITPs</em></a>
                    @break

                @case('morning-ask')
                    <x-button :href="$prompt('morning')">Begin</x-button>
                    {{-- brief 2: Breathe 11 --}}
                    @if ($soon)
                        <button type="button" class="btn-sec" disabled>Or sit a moment longer — <em>Breathe 11</em> · coming soon</button>
                    @else
                        <a class="btn-sec" href="{{ $itpUrl }}" data-transition="forward">Or sit a moment longer — <em>Breathe 11</em> · 2 min</a>
                    @endif
                    @break

                @case('morning-kept')
                    {{-- brief 2: Breathe 11 --}}
                    @if ($soon)
                        <x-button disabled meta="coming soon">Breathe 11</x-button>
                    @else
                        <x-button :href="$itpUrl">Breathe 11 · 2 min</x-button>
                    @endif
                    <a class="btn-sec" href="{{ $prompt('step') }}" data-transition="forward">Add one aligned step, if you wish</a>
                    @break

                @case('afternoon-return')
                @case('afternoon-why')
                    {{-- brief 2: Breathe 11 and Return to Coherence open in the player. --}}
                    <div class="rows">
                        @if ($soon)
                            <x-row disabled meta="coming soon">Breathe 11</x-row>
                        @else
                            <x-row :href="$itpUrl" :meta="$itpName.' · 2 min'">Breathe 11</x-row>
                        @endif
                        @if ($returnUrl === null)
                            <x-row disabled meta="coming soon">Return to Coherence</x-row>
                        @else
                            <x-row :href="$returnUrl" meta="primer · 2 min">Return to Coherence</x-row>
                        @endif
                    </div>
                    <a class="btn-sec" href="{{ $prompt('step') }}" data-transition="forward">Add one aligned step, if you wish</a>
                    @break

                @case('evening-ask')
                    <x-button :href="$prompt('evening')">Reflect</x-button>
                    <button type="button" class="btn-sec" wire:click="notTonight">Not tonight — <em>that’s allowed</em></button>
                    @break

                @case('evening-evidence')
                    <x-button :href="$prompt('evidence')">Add to the record</x-button>
                    @if (! $soon)
                        <button type="button" class="btn-sec" wire:click="enter">Or enter — <em>{{ $itpName }}</em> · 3 min</button>
                    @endif
                    @break

                @case('complete')
                @case('night-done')
                    @if ($soon)
                        <x-button disabled meta="coming soon">Enter</x-button>
                    @else
                        <x-button wire:click="enter">{{ $card['state'] === 'complete' ? 'Enter — '.$itpName : 'Enter' }}</x-button>
                    @endif
                    {{-- brief 4: the record --}}
                    <a class="btn-sec" href="{{ $links['record'] }}" data-transition="forward">Open <em>your record</em></a>
                    @break
            @endswitch
        </x-card>

        @if ($openGeneration !== null)
            @php
                $openTitle = match ($openStatus) {
                    \App\Api\GenerationStatus::AwaitingFollowup => 'A few questions are waiting for you.',
                    \App\Api\GenerationStatus::AwaitingResonance => 'Your attunement is ready to read.',
                    default => 'We’re still creating it.',
                };
            @endphp
            <x-card class="card--small" kicker="Your ITP is being made" :title="$openTitle">
                <x-button :href="route('generations.show', $openGeneration['id'], absolute: false)">Continue</x-button>
            </x-card>
        @endif
    </div>

    <div></div>

    @if ($downloadsPending)
        <div wire:poll.3s="downloadNext" class="sr-only">Downloading audio…</div>
    @endif
</div>
