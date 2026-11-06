<div class="px-6 py-10" data-swipe-back data-back-url="{{ route('home', absolute: false) }}">
    <a href="{{ route('home', absolute: false) }}" data-transition="back" class="text-sm text-stone-500">Home</a>

    @if ($imageUrl !== null)
        <img src="{{ $imageUrl }}" alt="" class="mt-4 h-56 w-full rounded-xl object-cover">
    @endif

    <h1 class="mt-6 text-2xl font-semibold">{{ $universalItp->display_name }}</h1>
    @if ($universalItp->description)
        <p class="mt-2 whitespace-pre-line text-stone-600">{{ $universalItp->description }}</p>
    @endif

    @if ($audios !== [])
        <section class="mt-8 flex flex-col gap-4">
            @foreach ($audios as $audio)
                <div wire:key="audio-{{ $loop->index }}" class="rounded-xl bg-white px-4 py-3 ring-1 ring-stone-200">
                    <span class="block text-sm font-medium">{{ $audio['label'] }}</span>
                    @switch($audio['status'])
                        @case('available')
                            <audio controls preload="none" src="{{ $audio['url'] }}" class="mt-2 w-full"></audio>
                            @break
                        @case('locked')
                            <a href="{{ route('sign-in', absolute: false) }}" data-transition="forward" class="mt-1 block text-sm text-stone-500">Sign in to play</a>
                            @break
                        @case('waiting')
                            <span class="mt-1 block text-sm text-stone-500">Downloads when you’re online.</span>
                            @break
                        @default
                            <span class="mt-1 block text-sm text-stone-500">Downloading…</span>
                    @endswitch
                </div>
            @endforeach
        </section>
    @endif

    @if ($paragraphs !== [])
        <section class="mt-10">
            <h2 class="text-sm font-medium uppercase tracking-wide text-stone-500">Attunement</h2>
            <div class="mt-3 flex flex-col gap-4 leading-relaxed">
                @foreach ($paragraphs as $paragraph)
                    <p class="whitespace-pre-line">{{ $paragraph }}</p>
                @endforeach
            </div>
        </section>
    @endif

    @if ($breathLines->isNotEmpty())
        <section class="mt-10">
            <h2 class="text-sm font-medium uppercase tracking-wide text-stone-500">Breathe 11</h2>
            <ol class="mt-3 flex flex-col gap-2">
                @foreach ($breathLines as $line)
                    <li wire:key="breath-{{ $line['order'] }}">{{ $line['text'] }}</li>
                @endforeach
            </ol>
        </section>
    @endif

    @if ($quantumWhys !== [])
        <section class="mt-10">
            <h2 class="text-sm font-medium uppercase tracking-wide text-stone-500">Quantum Whys</h2>
            <ul class="mt-3 flex flex-col gap-2">
                @foreach ($quantumWhys as $why)
                    <li wire:key="why-{{ $loop->index }}">{{ $why }}</li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
