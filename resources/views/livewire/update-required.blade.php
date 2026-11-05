<div class="flex min-h-dvh items-center justify-center px-6 text-center">
    <div class="max-w-sm">
        <h1 class="text-2xl font-semibold">Time for an update</h1>

        @if ($isMobile)
            <p class="mt-3 text-stone-600">This version of the app is no longer supported. Update it from the App Store or Google Play. Everything on your phone is safe.</p>
        @else
            <p class="mt-3 text-stone-600">A new version is available. Reload the page to continue.</p>
        @endif
    </div>
</div>
