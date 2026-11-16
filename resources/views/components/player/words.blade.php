{{--
    Timed sentences, from itpmobile's sections/player/words: every sentence on top of the others,
    each in two layers, the crisp words over their blurred ghosts. player.js shows a sentence
    (data-state: hidden → in → out) and lifts each word's ghost away (data-up) as it is spoken.
    Times are in seconds of the voice.

    <x-player.words :sentences="$sentences" />
--}}
@props(['sentences'])
<div {{ $attributes->class(['player-words']) }}>
    @foreach ($sentences as $index => $sentence)
        <div class="player-words__slot" wire:key="sentence-{{ $index }}">
            <div class="player-sentence" data-sentence="{{ $index }}" data-start="{{ $sentence['start'] }}" data-end="{{ $sentence['end'] }}" data-state="hidden">
                <div class="player-sentence__text">
                    @foreach ($sentence['words'] as $word)
                        <span>{{ $word['word'] }}</span>
                    @endforeach
                </div>
                <div class="player-sentence__ghosts" aria-hidden="true">
                    @foreach ($sentence['words'] as $wordIndex => $word)
                        <span class="player-word" data-word="{{ $index }}-{{ $wordIndex }}" data-start="{{ $word['start'] }}"><span class="player-word__ghost">{{ $word['word'] }}</span></span>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
    {{ $slot }}
</div>
