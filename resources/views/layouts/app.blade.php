<!DOCTYPE html>
{{-- is-native comes from the platform flag only (platform.md §3a); CSS and transitions.js read it. --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['is-native' => \App\Support\Runtime::isMobile()])>
@include('partials.document-head')
<body class="app-body bg-stone-50 font-sans text-stone-900 antialiased">
    {{-- The only thing that animates. Each page renders inside it. --}}
    <main class="app-page app-page--with-tabs">
        {{ $slot }}
    </main>

    {{-- Persisted, so playback carries on across navigations. The player is ported from itpmobile later. --}}
    @persist('player')
        <div class="app-player" data-player></div>
    @endpersist

    {{-- Persisted: same DOM node across navigations, never animates. transitions.js keeps aria-current right.
         Paths, not absolute URLs: on the phone Laravel builds http://127.0.0.1 links for a page served
         from php://127.0.0.1, and Home's absolute URL has no path at all. --}}
    @persist('tabbar')
        <nav class="app-tabbar border-t border-stone-200 bg-white text-sm text-stone-500" aria-label="Main">
            <a href="{{ route('home', absolute: false) }}" data-transition="tab" data-tab-match="/" class="aria-[current=page]:font-semibold aria-[current=page]:text-stone-900">Home</a>
            <a href="{{ route('account', absolute: false) }}" data-transition="tab" data-tab-match="/account" class="aria-[current=page]:font-semibold aria-[current=page]:text-stone-900">Account</a>
        </nav>
    @endpersist
</body>
</html>
