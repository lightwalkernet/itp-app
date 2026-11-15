<!DOCTYPE html>
{{-- is-native comes from the platform flag only (platform.md §3a); CSS and transitions.js read it.
     data-phase / data-room / data-bar are set by field.js and rooms.js from the clock and the page. --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['is-native' => \App\Support\Runtime::isMobile()])>
@include('partials.document-head')
<body class="app-body antialiased">
    {{-- The field of light, behind every page. Persisted so the light never restarts; drawn by field.js
         (docs/architecture/field-persistence.md). A page declares data-room="white" on its root to put it away. --}}
    @persist('field')
        <x-field />
    @endpersist

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
        <x-bar />
    @endpersist
</body>
</html>
