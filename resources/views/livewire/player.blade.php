{{--
    The player (player contract), ported from itpmobile's livewire/player/{activation,introduction,
    breath11,why}. A light room with the bar away. The stage is wire:ignore: once it plays, only
    player.js changes it, every frame off audio.currentTime. Finishing (or stopping) asks the
    server where to go, which dispatches player-navigate.
--}}
@php
    $isIntroduction = $itp instanceof \Itp\Core\Models\UniversalItp && $itp->slug === \App\Support\Welcome::INTRODUCTION_SLUG && $piece === 'itp';
    $welcome = $from === 'welcome';
@endphp
<div data-room="light" data-bar="hidden" class="player"
     data-piece="{{ $piece }}"
     x-data
     @if ($from === 'today' && $piece === 'itp') x-init="itpSyncClock($wire)" @endif
     x-on:player-navigate.window="itpPlayerNavigate($event.detail)">

    @if ($unavailable !== null)
        <div class="col player-unavailable">
            <p class="t-lit">{{ $name }}</p>
            <p class="t-hint" data-unavailable>{{ $unavailable }}</p>
            @if ($welcome)
                <button type="button" class="back" wire:click="finish"><x-player.icon name="chevron-left" /> Continue</button>
            @else
                <a class="back" href="{{ $finishUrl }}" data-transition="back"><x-player.icon name="chevron-left" /> Back</a>
            @endif
        </div>
    @elseif ($piece === 'itp')
        <div wire:ignore class="player-stage" x-data="itpWordsPlayer({ marksTheDay: @js($from === 'today') })" :class="{ 'is-paused': paused }">
            @if ($isIntroduction)
                <div class="player-mark" aria-hidden="true">
                    <x-player.icon name="itp" />
                    <x-player.icon name="itp" class="player-mark__ghost" />
                </div>
            @endif

            <x-player.play />

            <div class="player-stage__middle">
                <x-player.words :sentences="$sentences" />
            </div>

            @if ($welcome)
                <div class="player-skip">
                    <button type="button" class="player-skip__button" x-on:click="stop()">skip intro?</button>
                </div>
            @else
                <x-player.controls />
            @endif

            <audio x-ref="voice" preload="auto" src="{{ $audioUrl }}"></audio>
        </div>
    @elseif ($piece === 'breathe')
        <div wire:ignore class="player-stage" x-data="itpBreathePlayer()" :class="{ 'is-paused': paused }">
            <x-player.play />

            <div class="player-stage__middle">
                <div class="col">
                    <div class="breath" :class="{ 'is-shown': started }">
                        <div class="breath__words">
                            <x-player.words :sentences="$sentences" class="player-words--breathe">
                                <div class="player-words__slot">
                                    <div class="player-sentence" data-itis data-state="hidden">
                                        <div class="player-sentence__text">And so it is</div>
                                        <div class="player-sentence__ghosts" aria-hidden="true"><span class="player-word__ghost">And so it is</span></div>
                                    </div>
                                </div>
                            </x-player.words>
                        </div>
                        <div class="breath__ripple" x-ref="ripple"></div>
                    </div>
                    <div class="breath__label" aria-live="polite">
                        <span class="breath__label-text" x-text="label"></span>
                        <span class="breath__label-ghost" aria-hidden="true" x-text="label"></span>
                    </div>
                </div>
            </div>

            <x-player.controls />

            <audio x-ref="voice" preload="auto" src="{{ $audioUrl }}"></audio>
            @foreach ($sounds as $sound => $url)
                <audio data-sound="{{ $sound }}" preload="auto" src="{{ $url }}" wire:key="sound-{{ $sound }}"></audio>
            @endforeach
        </div>
    @else
        <div wire:ignore class="player-stage" x-data="itpWhyPlayer()" data-whys-key="{{ $storageKey }}">
            <ol hidden data-whys>
                @foreach ($whys as $index => $why)
                    <li wire:key="why-{{ $index }}">{{ $why }}</li>
                @endforeach
            </ol>

            <div class="player-stage__middle">
                <div class="player-words player-words--why">
                    <div class="player-words__slot">
                        <div class="player-sentence" x-ref="why" data-state="hidden">
                            <div class="player-sentence__text" x-text="why"></div>
                            <div class="player-sentence__ghosts" aria-hidden="true"><span class="player-word__ghost" x-text="why"></span></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="player-controls" :class="{ 'is-shown': controlsShown }">
                <button type="button" class="player-control" x-on:click="stop()" aria-label="Back">
                    <span class="player-control__inner"><x-player.icon name="chevron-left" /></span>
                </button>
            </div>
        </div>
    @endif
</div>
