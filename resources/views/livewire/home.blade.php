<div class="px-6 py-10" wire:init="refresh">
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
</div>
