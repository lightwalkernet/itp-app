<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ isset($title) ? $title.' · ' : '' }}it's totally possible</title>
    {{-- The time of day and still mode, before the first frame, so a fresh load starts in the right light
         instead of the default night and fading from it. The same rules as field.js (phaseFor, itp.still).
         The device's time zone goes in a cookie, so the server's first render of Today already follows
         the person's clock (KnowsTheDeviceClock) instead of flipping when the device clock arrives. --}}
    <script>
        (() => {
            const root = document.documentElement, hour = new Date().getHours();
            root.dataset.phase = hour >= 5 && hour <= 8 ? 'dawn' : hour >= 9 && hour <= 16 ? 'day' : hour >= 17 && hour <= 20 ? 'dusk' : 'night';
            try { if (localStorage.getItem('itp.still') === '1') root.setAttribute('data-still', '') } catch {}
            try { document.cookie = 'itp_tz=' + encodeURIComponent(Intl.DateTimeFormat().resolvedOptions().timeZone) + '; path=/; max-age=31536000; samesite=lax' } catch {}
        })()
    </script>
    {{-- Every file in public/ goes through asset(): on device only URLs under _assets are served (platform.md §7). --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
