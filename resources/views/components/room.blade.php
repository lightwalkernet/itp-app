{{--
    The head of a white room: a kicker and the room's name, on the paper itself.

    <x-room kicker="Everything you can enter" title="Your ITPs" />
--}}
@props(['kicker' => null, 'title'])
<header {{ $attributes->class(['room']) }}>
    @if ($kicker)
        <p class="t-kicker">{{ $kicker }}</p>
    @endif
    <h1 class="t-room">{{ $title }}</h1>
</header>
