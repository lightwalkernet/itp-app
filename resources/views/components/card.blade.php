{{--
    A white card on the field: the one thing to do, or something to read.

    <x-card kicker="This morning" title="The Quiet Yes" :quote="false">
        <p class="t-body">Enter it first…</p>
        <x-button>Enter</x-button>
    </x-card>
--}}
@props(['kicker' => null, 'title' => null, 'quote' => false])
<div {{ $attributes->class(['card']) }}>
    @if ($kicker)
        <p class="t-kicker">{{ $kicker }}</p>
    @endif
    @if ($title)
        <h2 @class(['t-title', 't-title--quote' => $quote])>{{ $title }}</h2>
    @endif
    {{ $slot }}
</div>
