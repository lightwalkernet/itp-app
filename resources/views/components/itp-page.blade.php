{{--
    An ITP's page (brief 1 §5): one template for a universal ITP and a personal one. The Livewire
    page (ShowUniversalItp, ShowItp) works out what to show through App\Livewire\Concerns\ShowsAnItp
    and owns the "live with this one" action.

    <x-itp-page :itp="$itp" :image-url="$imageUrl" :paragraphs="$paragraphs" :breath-lines="$breathLines"
                :quantum-whys="$quantumWhys" :enter="$enter" :breath-audio="$breathAudio"
                :living-with="$livingWith" :locked-for-guest="$lockedForGuest" :open-to-guests="$openToGuests" />

    A fold with nothing in it is left out: primers and audio-only pieces have no words.
--}}
@props([
    'itp',
    'imageUrl' => null,
    'paragraphs' => [],
    'breathLines' => [],
    'quantumWhys' => [],
    'enter' => null,
    'breathAudio' => null,
    'livingWith' => null,
    'lockedForGuest' => false,
    'openToGuests' => null,
])
@php
    $isPersonal = $itp instanceof \Itp\Core\Models\Itp;
    $kicker = $isPersonal
        ? 'Made from your words · '.$itp->created_at->format('j F')
        : (filled($itp->short_name) && $itp->short_name !== $itp->title ? $itp->short_name : null);
    $lines = preg_split('/\R/', trim((string) $itp->description), 2);
    [$ask, $rest] = $isPersonal || count($lines) < 2 ? [null, $itp->description] : $lines;
    $libraryUrl = route('itps.index', absolute: false);
    $statusLine = ['downloading' => 'Downloading…', 'waiting' => 'Downloads when you’re online.'];
    $statusMeta = ['downloading' => 'downloading…', 'waiting' => 'waiting for a connection'];
@endphp
<div data-room="white" data-swipe-back data-back-url="{{ $libraryUrl }}">
    <div class="col col--room">
        <a href="{{ $libraryUrl }}" data-transition="back" class="back"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg> Your ITPs</a>

        @if ($imageUrl !== null)
            <img src="{{ $imageUrl }}" alt="" class="emblem">
        @endif

        @if ($kicker !== null)
            <p class="t-kicker itp-kicker">{{ $kicker }}</p>
        @endif
        <h1 @class(['t-room', 'itp-title', 'itp-title--personal' => $isPersonal])>{{ $itp->title }}</h1>

        @if (filled($ask) || filled($rest))
            <p class="t-body t-body--lg itp-desc">@if (filled($ask))<i class="itp-desc__ask">{{ $ask }}</i>@endif{{ $rest }}</p>
        @endif

        {{-- brief 2: Enter opens the player. Until then it plays the attunement's audio here. --}}
        @if ($enter !== null)
            @switch($enter['status'])
                @case('available')
                    <div x-data>
                        <x-button meta="attunement · 3 min" x-on:click="$refs.audio.paused ? $refs.audio.play() : $refs.audio.pause()">Enter</x-button>
                        <audio controls preload="none" src="{{ $enter['url'] }}" x-ref="audio" class="itp-audio"></audio>
                    </div>
                    @break
                @case('locked')
                    <x-button href="{{ route('sign-in', absolute: false) }}" meta="email and a code">Sign in to listen</x-button>
                    @if ($lockedForGuest && $openToGuests !== null)
                        <p class="t-body t-soft itp-note">You can read the words below first. {{ \App\Support\NumberWords::spellCapitalised($openToGuests) }} Universals are open to everyone; this one waits for you after sign-in.</p>
                    @endif
                    @break
                @default
                    <x-button :disabled="true" :meta="$statusMeta[$enter['status']]">Enter</x-button>
                    <p class="t-meta itp-status">{{ $statusLine[$enter['status']] }}</p>
            @endswitch
        @endif

        @if ($livingWith === 'living')
            <p class="tabnote itp-living">You’re living with this one.</p>
        @elseif ($livingWith === 'offer')
            <button type="button" class="btn-sec" wire:click="liveWithThis">Live with this one <em>instead</em></button>
        @endif

        {{-- brief 2: Breathe 11 and the Quantum Why screen --}}
        @if ($breathLines !== [] || $quantumWhys !== [])
            <div class="rows">
                @if ($breathLines !== [])
                    <x-row :meta="\App\Support\NumberWords::spell(count($breathLines)).' '.\Illuminate\Support\Str::plural('line', count($breathLines)).' · 2 min'" aria-disabled="true">Breathe 11</x-row>
                @endif
                @if ($quantumWhys !== [])
                    <x-row :meta="'one of '.\App\Support\NumberWords::spell(count($quantumWhys))" aria-disabled="true">A Quantum Why</x-row>
                @endif
            </div>
        @endif

        <div class="itp-folds">
            @if ($paragraphs !== [])
                <x-fold title="The words" meta="read before you listen, or after">
                    @foreach ($paragraphs as $paragraph)
                        <p @class(['whitespace-pre-line', 'o' => $loop->first])>{{ $paragraph }}</p>
                    @endforeach
                </x-fold>
            @endif

            @if ($breathLines !== [] || ($breathAudio !== null && $breathAudio['status'] !== 'locked'))
                <x-fold title="The eleven" meta="Breathe 11">
                    @if ($breathAudio !== null)
                        @if ($breathAudio['status'] === 'available')
                            <audio controls preload="none" src="{{ $breathAudio['url'] }}" class="itp-audio"></audio>
                        @elseif ($breathAudio['status'] !== 'locked')
                            <p class="t-meta">{{ $statusLine[$breathAudio['status']] }}</p>
                        @endif
                    @endif
                    @foreach ($breathLines as $line)
                        <p wire:key="breath-{{ $loop->index }}">{{ $line }}</p>
                    @endforeach
                </x-fold>
            @endif

            @if ($quantumWhys !== [])
                <x-fold title="Quantum Whys" :meta="(string) count($quantumWhys)">
                    @foreach ($quantumWhys as $why)
                        <p class="o" wire:key="why-{{ $loop->index }}">{{ $why }}</p>
                    @endforeach
                </x-fold>
            @endif
        </div>
    </div>
</div>
