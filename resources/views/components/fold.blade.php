{{--
    A fold on white: a <details> with a name, a quiet note, and its body.

    <x-fold title="The words" meta="read before you listen, or after">
        <p>…</p>
    </x-fold>
--}}
@props(['title', 'meta' => null, 'open' => false])
<details {{ $attributes->class(['fold']) }} @if ($open) open @endif>
    <summary>
        <span>{{ $title }}@if ($meta) <small>{{ $meta }}</small>@endif</span>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
    </summary>
    <div class="fold__body">{{ $slot }}</div>
</details>
