<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ isset($title) ? $title.' · ' : '' }}it's totally possible</title>
    {{-- Every file in public/ goes through asset(): on device only URLs under _assets are served (platform.md §7). --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
