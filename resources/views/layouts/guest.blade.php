<!DOCTYPE html>
{{-- is-native comes from the platform flag only (platform.md §3a); CSS and transitions.js read it. --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['is-native' => \App\Support\Runtime::isMobile()])>
@include('partials.document-head')
<body class="app-body bg-stone-50 font-sans text-stone-900 antialiased">
    <main class="app-page">
        {{ $slot }}
    </main>
</body>
</html>
