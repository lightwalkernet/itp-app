{{--
    The primary action. A <button> by default; an <a> when given href (with data-transition for the page slide).

    <x-button href="{{ route('…', absolute: false) }}" transition="forward" meta="attunement · 3 min">Enter</x-button>
    <x-button variant="ghost">Make a new personal ITP</x-button>
    <x-button variant="light">Begin</x-button>          (on the field, not on white)
--}}
@props(['href' => null, 'variant' => 'primary', 'meta' => null, 'transition' => 'forward', 'type' => 'button'])
@php
    $classes = ['btn', 'btn--ghost' => $variant === 'ghost', 'btn--light' => $variant === 'light'];
@endphp
@if ($href)
    <a href="{{ $href }}" data-transition="{{ $transition }}" {{ $attributes->class($classes) }}>{{ $slot }}@if ($meta) <small>{{ $meta }}</small>@endif</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}@if ($meta) <small>{{ $meta }}</small>@endif</button>
@endif
