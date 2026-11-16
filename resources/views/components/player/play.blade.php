{{--
    The big play button, from itpmobile's sections/player/play. Phones only let audio start from a
    tap, so playback always begins here. Inside an Alpine player (player.js).
--}}
<div class="player-play" x-show="! started" x-transition.opacity.duration.500ms>
    <button type="button" class="player-play__button" x-on:click="failed = false; start()" aria-label="Play">
        <span class="player-play__inner"><x-player.icon name="play" class="player-icon--xl" /></span>
    </button>
    <p class="t-hint player-play__failed" x-show="failed" x-cloak>This one can’t play right now. Try again in a moment.</p>
</div>
