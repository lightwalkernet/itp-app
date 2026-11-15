{{--
    The bar: Today · Return · ITPs · You (design/direction.md "Navigation").

    Rendered once inside @persist('tabbar') in the layout. Links use data-transition="tab" and a
    data-tab-match prefix, which transitions.js uses to keep aria-current right after each swap
    (a persisted element never re-renders). Paths, not absolute URLs (see the layout).

    Return is not a page: it opens a sheet with the ways back to centre. Its rows will point at the
    player once that route exists; until then they lead home.

    Signing in or out is a full page load, so the persisted bar is rebuilt for the new state.
--}}
@php
    $itpsUrl = Route::has('itps.index') ? route('itps.index', absolute: false) : route('home', absolute: false);
    $youUrl = Auth::check() ? route('account', absolute: false) : route('sign-in', absolute: false);
    $youMatch = Auth::check() ? '/account' : '/sign-in';
    $returnUrl = route('home', absolute: false);
@endphp
<nav class="app-tabbar" aria-label="Main" x-data="{ open: false }" @keydown.escape.window="open = false">
    <a href="{{ route('home', absolute: false) }}" data-transition="tab" data-tab-match="/">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 17h18M6 17a6 6 0 0 1 12 0"/><circle cx="12" cy="11" r="1.2" fill="currentColor"/></svg>
        Today
    </a>
    <button type="button" @click="open = !open" :aria-expanded="open ? 'true' : 'false'" aria-controls="return-sheet">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M12 7v5l3 2"/></svg>
        Return
    </button>
    <a href="{{ $itpsUrl }}" data-transition="tab" data-tab-match="/itps">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 20V6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v14l-6-3-6 3z"/></svg>
        ITPs
    </a>
    <a href="{{ $youUrl }}" data-transition="tab" data-tab-match="{{ $youMatch }}">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8.5" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0"/></svg>
        You
    </a>

    <template x-teleport="body">
        <div x-show="open" x-cloak>
            <div class="return-scrim" @click="open = false"
                 x-show="open"
                 x-transition:enter="return-scrim-enter" x-transition:enter-start="return-scrim-enter-start"
                 x-transition:leave="return-scrim-leave" x-transition:leave-end="return-scrim-leave-end"></div>
            <div id="return-sheet" class="return-sheet" role="dialog" aria-label="A way back to centre"
                 x-show="open" x-trap.noscroll="open"
                 x-transition:enter="return-sheet-enter" x-transition:enter-start="return-sheet-enter-start"
                 x-transition:leave="return-sheet-leave" x-transition:leave-end="return-sheet-leave-end">
                <p class="t-kicker">Whenever you need it</p>
                <h2 class="t-title" style="font-size: 26px">A way back to centre</h2>
                <div class="rows">
                    <a class="row" href="{{ $returnUrl }}" data-transition="forward" @click="open = false">Return to Coherence <small>primer · 2 min</small></a>
                    <a class="row" href="{{ $returnUrl }}" data-transition="forward" @click="open = false">Breathe 11 <small>2 min</small></a>
                    <a class="row" href="{{ $returnUrl }}" data-transition="forward" @click="open = false">The afternoon’s Why <small>a moment</small></a>
                </div>
            </div>
        </div>
    </template>
</nav>
