<div class="px-6 py-10" data-swipe-back data-back-url="{{ route('home', absolute: false) }}">
    <a href="{{ route('home', absolute: false) }}" data-transition="back" class="text-sm text-stone-500">Home</a>

    <h1 class="mt-4 text-2xl font-semibold">{{ $itp->title }}</h1>
    @if ($itp->description)
        <p class="mt-2 text-stone-600">{{ $itp->description }}</p>
    @endif

    <section class="mt-10">
        <h2 class="text-sm font-medium uppercase tracking-wide text-stone-500">Attunement</h2>
        <div class="mt-3 flex flex-col gap-4 leading-relaxed">
            @foreach ($paragraphs as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
        </div>
    </section>

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
