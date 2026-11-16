{{--
    The bar: the original app's glass pill (docs/design/direction.md "Navigation"). Your ITPs on
    the left, the logo raised in the centre for Today, You on the right (a guest's goes to sign-in).
    No visible labels, as in the original; each link has an aria-label.

    Rendered once inside @persist('tabbar') in the layout. Links use data-transition="tab" and
    data-tab-match (one or more path prefixes, space-separated), which transitions.js uses to keep
    aria-current right after each swap (a persisted element never re-renders). An ITP's page belongs
    to Your ITPs; About and account deletion to You. Paths, not absolute URLs (see the layout). The
    links are in the order they sit on screen, so a tab slides the way it looks.

    The logo's blurred copy is its glow when Today is the current page (bar.css).

    Signing in or out is a full page load, so the persisted bar is rebuilt for the new state.
--}}
@php
    $youUrl = Auth::check() ? route('you', absolute: false) : route('sign-in', absolute: false);
    $youMatch = Auth::check() ? '/you /about /delete-account' : '/sign-in';
    $youLabel = Auth::check() ? 'You' : 'Sign in';
@endphp
<nav class="app-tabbar" aria-label="Main">
    <a href="{{ route('itps.index', absolute: false) }}" data-transition="tab" data-tab-match="/itps /universal" class="app-tabbar__side app-tabbar__side--start" aria-label="Your ITPs">
        <x-icons.list />
    </a>
    <a href="{{ route('home', absolute: false) }}" data-transition="tab" data-tab-match="/ /today" class="app-tabbar__logo" aria-label="Today">
        <x-icons.logo class="app-tabbar__glow" />
        <x-icons.logo class="app-tabbar__mark" />
    </a>
    <a href="{{ $youUrl }}" data-transition="tab" data-tab-match="{{ $youMatch }}" class="app-tabbar__side app-tabbar__side--end" aria-label="{{ $youLabel }}">
        <x-icons.user />
    </a>
</nav>
