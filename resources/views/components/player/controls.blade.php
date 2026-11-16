{{--
    Stop, play/pause and restart, from itpmobile's sections/player/controls. Glass over the field;
    they appear a second after playback starts. Inside an Alpine player (player.js).
--}}
<div class="player-controls" :class="{ 'is-shown': controlsShown }">
    <button type="button" class="player-control" x-on:click="stop()" aria-label="Stop">
        <span class="player-control__inner"><x-player.icon name="stop" /></span>
    </button>
    <button type="button" class="player-control" x-on:click="toggle()" :aria-label="paused ? 'Play' : 'Pause'">
        <span class="player-control__inner">
            <x-player.icon name="play" class="player-icon--lg" x-show="paused" x-cloak />
            <x-player.icon name="pause" class="player-icon--lg" x-show="! paused" />
        </span>
    </button>
    <button type="button" class="player-control" x-on:click="restart()" aria-label="Restart">
        <span class="player-control__inner"><x-player.icon name="restart" /></span>
    </button>
</div>
