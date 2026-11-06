<div class="px-6 py-10" wire:init="refresh">
    @if ($user !== null)
        <div class="flex items-start justify-between gap-4">
            <h1 class="text-2xl font-semibold">Hello, {{ $user->first_name }}</h1>

            <button type="button" wire:click="refresh" class="rounded-lg px-3 py-1.5 text-sm text-stone-500 ring-1 ring-stone-200 disabled:opacity-50" wire:loading.attr="disabled" wire:target="refresh">
                <span wire:loading.remove wire:target="refresh">Refresh</span>
                <span wire:loading wire:target="refresh">Refreshing…</span>
            </button>
        </div>

        @if ($openGeneration !== null)
            <a href="{{ route('generations.show', $openGeneration['id'], absolute: false) }}" data-transition="forward" class="mt-6 block rounded-xl bg-stone-900 px-4 py-4 text-white">
                <span class="block font-medium">Continue your ITP</span>
                <span class="mt-1 block text-sm text-stone-300">
                    @switch($openStatus)
                        @case(\App\Api\GenerationStatus::AwaitingFollowup)
                            A few questions are waiting for you.
                            @break
                        @case(\App\Api\GenerationStatus::AwaitingResonance)
                            Your attunement is ready to read.
                            @break
                        @default
                            We’re still creating it.
                    @endswitch
                </span>
            </a>
        @endif

        <a href="{{ route('itps.create', absolute: false) }}" data-transition="forward" class="mt-6 block rounded-xl bg-white px-4 py-4 font-medium ring-1 ring-stone-200">
            Create a personal ITP
        </a>

        <h2 class="mt-10 text-sm font-medium uppercase tracking-wide text-stone-500">Your ITPs</h2>

        @if ($itps->isEmpty())
            <p class="mt-3 text-stone-600">Your ITPs will appear here.</p>
        @else
            <ul class="mt-3 divide-y divide-stone-200 rounded-xl bg-white ring-1 ring-stone-200">
                @foreach ($itps as $itp)
                    <li wire:key="itp-{{ $itp->id }}">
                        <a href="{{ route('itps.show', $itp, absolute: false) }}" data-transition="forward" class="block px-4 py-3">
                            <span class="block font-medium">{{ $itp->title }}</span>
                            @if ($itp->description)
                                <span class="mt-0.5 block text-sm text-stone-600">{{ $itp->description }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    @else
        <h1 class="text-2xl font-semibold">it’s totally possible</h1>
        <p class="mt-2 text-stone-600">Choose a Universal ITP that calls to you.</p>

        <a href="{{ route('sign-in', absolute: false) }}" data-transition="forward" class="mt-6 block rounded-xl bg-stone-900 px-4 py-4 text-center font-medium text-white">
            Sign in or create a free account
        </a>
    @endif

    <h2 class="mt-10 text-sm font-medium uppercase tracking-wide text-stone-500">Universal ITPs</h2>

    @if ($universals->isEmpty())
        <p class="mt-3 text-stone-600">The universal ITPs will appear here.</p>
    @else
        <ul class="mt-3 flex flex-col gap-3">
            @foreach ($universals as $universal)
                @php($isLocked = $user === null && ! $universal->is_preview)
                @php($imageUrl = $media->url($universal->image_path))
                <li wire:key="universal-{{ $universal->id }}">
                    <a href="{{ $isLocked ? route('sign-in', absolute: false) : route('universals.show', $universal, absolute: false) }}" data-transition="forward" class="flex items-center gap-4 overflow-hidden rounded-xl bg-white ring-1 ring-stone-200">
                        <span class="relative h-20 w-24 shrink-0 bg-stone-200">
                            @if ($imageUrl !== null)
                                <img src="{{ $imageUrl }}" alt="" class="h-full w-full object-cover" loading="lazy">
                            @endif
                            @if ($isLocked)
                                <span class="absolute bottom-1 left-1 rounded-full bg-stone-900/60 p-1 text-white" data-locked>
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd"/></svg>
                                </span>
                            @endif
                        </span>
                        <span class="min-w-0 py-3 pr-4">
                            <span class="block font-medium">{{ $universal->display_name }}</span>
                            @if ($universal->description)
                                <span class="mt-0.5 line-clamp-2 block text-sm text-stone-600">{{ $universal->description }}</span>
                            @endif
                            @if ($isLocked)
                                <span class="mt-1 block text-xs font-medium uppercase tracking-wide text-stone-500">Sign in to play</span>
                            @endif
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($downloadsPending)
        <div wire:poll.3s="downloadNext" class="sr-only">Downloading audio…</div>
    @endif
</div>
