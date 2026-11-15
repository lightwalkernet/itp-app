{{--
    A row in a list on white: a name on the left, a quiet note on the right. A link when given href.

    <div class="rows">
        <x-row href="…" meta="what you’ve kept">Your record</x-row>
    </div>
--}}
@props(['href' => null, 'meta' => null, 'transition' => 'forward', 'type' => 'button'])
@if ($href)
    <a href="{{ $href }}" data-transition="{{ $transition }}" {{ $attributes->class(['row']) }}>{{ $slot }}@if ($meta) <small>{{ $meta }}</small>@endif</a>
@else
    <button type="{{ $type }}" {{ $attributes->class(['row']) }}>{{ $slot }}@if ($meta) <small>{{ $meta }}</small>@endif</button>
@endif
