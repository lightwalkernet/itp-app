{{--
    An ITP's page (player contract item 4): one template for a universal ITP and a personal one.
    The Livewire page (ShowUniversalItp, ShowItp) works out what to show through
    App\Livewire\Concerns\ShowsAnItp and owns the Make this my ITP action.

    <x-itp-page :itp="$itp" :image-url="$imageUrl" :choice="$choice" :pieces="$pieces"
                :locked-for-guest="$lockedForGuest" :open-to-guests="$openToGuests" />

    The heading is the short name (a personal ITP: its title, in italic). The pieces open the
    player and never touch the day. The words are in the player, not here.
--}}
@props([
    'itp',
    'imageUrl' => null,
    'choice' => null,
    'pieces' => [],
    'lockedForGuest' => false,
    'openToGuests' => null,
])
@php
    $isPersonal = $itp instanceof \Itp\Core\Models\Itp;
    $lines = preg_split('/\R/', trim((string) $itp->description), 2);
    [$ask, $rest] = $isPersonal || count($lines) < 2 ? [null, $itp->description] : $lines;
    $libraryUrl = route('itps.index', absolute: false);
@endphp
<div data-room="white" data-swipe-back data-back-url="{{ $libraryUrl }}">
    <div class="col col--room">
        <a href="{{ $libraryUrl }}" data-transition="back" class="back"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg> Your ITPs</a>

        @if ($imageUrl !== null)
            <img src="{{ $imageUrl }}" alt="" class="emblem">
        @endif

        @if ($isPersonal)
            <p class="t-kicker itp-kicker">Made from your words · {{ $itp->created_at->format('j F') }}</p>
        @endif
        <h1 @class(['t-room', 'itp-title', 'itp-title--personal' => $isPersonal])>{{ $isPersonal ? $itp->title : $itp->display_name }}</h1>

        @if (filled($ask) || filled($rest))
            <p class="t-body t-body--lg itp-desc">@if (filled($ask))<i class="itp-desc__ask">{{ $ask }}</i>@endif{{ $rest }}</p>
        @endif

        @if ($lockedForGuest)
            <x-button href="{{ route('sign-in', absolute: false) }}" meta="email and a code">Sign in to play</x-button>
            @if ($openToGuests !== null)
                <p class="t-body t-soft itp-note">{{ \App\Support\NumberWords::spellCapitalised($openToGuests) }} Universals are open to everyone; this one waits for you after sign-in.</p>
            @endif
        @else
            @if ($choice === 'chosen')
                <p class="tabnote itp-chosen">This is your ITP.</p>
            @elseif ($choice === 'offer')
                <x-button wire:click="makeThisMyItp">Make this my ITP</x-button>
            @endif

            @if ($pieces !== [])
                <div class="itp-play" role="group" aria-label="Play">
                    @foreach ($pieces as $piece)
                        @if ($piece['url'] !== null)
                            <x-button variant="ghost" :href="$piece['url']" :meta="$piece['meta']" class="itp-play__piece" data-piece="{{ $piece['piece'] }}" wire:key="piece-{{ $piece['piece'] }}">{{ $piece['label'] }}</x-button>
                        @else
                            <x-button variant="ghost" :disabled="true" :meta="$piece['meta']" class="itp-play__piece" data-piece="{{ $piece['piece'] }}" wire:key="piece-{{ $piece['piece'] }}">{{ $piece['label'] }}</x-button>
                        @endif
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</div>
