@php($backUrl = auth()->check() ? route('you', absolute: false) : route('home', absolute: false))
<div data-room="white" data-swipe-back data-back-url="{{ $backUrl }}">
    <div class="col col--room">
        <a href="{{ $backUrl }}" data-transition="back" class="back"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg> {{ auth()->check() ? 'You' : 'Today' }}</a>

        <x-room kicker="About" title="It’s Totally Possible" />

        {{-- Creative Codex v6, §1 "The Spirit of ITP" (docs/codex/creative-codex-v6.md), verbatim. --}}
        <p class="t-title about-lead">ITP is not content. It is a field.</p>
        <p class="t-body t-body--lg about-body">A transmission of possibility, presence and gentle expansion that lands as real. Grounded and quietly extraordinary, ITP does not try to convince people into a better life. It helps them return to the part of themselves that already knows more is possible — and then creates the inner conditions for that knowing to become embodied.</p>
    </div>
</div>
