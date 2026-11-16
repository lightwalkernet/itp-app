{{--
    The welcome on first launch, from itpmobile's sections/welcome. A light room with the bar away.
    Enter the Field plays the Introduction; finishing or skipping it goes on to Today.
--}}
<div data-room="light" data-bar="hidden" class="welcome">
    <div class="welcome__inner">
        <h1 class="welcome__title">
            <span>It’s</span>
            <span>totally</span>
            <span>possible</span>
        </h1>

        <a href="{{ $enterUrl }}" data-transition="forward" class="welcome__enter">
            <span class="welcome__enter-inner">Enter the Field</span>
        </a>
    </div>
</div>
