<div data-room="white">
    <div class="col col--room">
        <x-room kicker="Everything you can enter" title="Your ITPs" />

        <div class="tabs" role="tablist">
            <button type="button" class="tab" role="tab" aria-selected="{{ $currentTab === 'universal' ? 'true' : 'false' }}" wire:click="selectTab('universal')">Universal <small>{{ $universalCount }}</small></button>
            <button type="button" class="tab" role="tab" aria-selected="{{ $currentTab === 'personal' ? 'true' : 'false' }}" wire:click="selectTab('personal')">Personal <small>{{ $itps?->count() }}</small></button>
            <button type="button" class="tab" role="tab" aria-selected="{{ $currentTab === 'primers' ? 'true' : 'false' }}" wire:click="selectTab('primers')">Primers <small>{{ $primerCount }}</small></button>
        </div>

        @switch($currentTab)
            @case('personal')
                <p class="tabnote">Made from your words, for you alone.</p>

                @if ($user === null)
                    <p class="t-body library-invite">Your own ITP is made from your words — a few questions, answered in your own time.</p>
                    <p class="t-meta">After you sign in. Email and a six-digit code; no password.</p>
                    <x-button href="{{ route('sign-in', absolute: false) }}">Sign in</x-button>
                @else
                    <div class="library-list">
                        @foreach ($itps as $itp)
                            @php($thumbUrl = $media->url($itp->image_path))
                            @php($meta = 'Made from your words · '.$itp->created_at->format('j F').($livingWith?->is($itp) ? ' · living with' : ''))
                            <a href="{{ route('itps.show', $itp, absolute: false) }}" data-transition="forward" class="lrow" wire:key="itp-{{ $itp->id }}">
                                @if ($thumbUrl !== null)
                                    <img src="{{ $thumbUrl }}" alt="" class="lrow__thumb" loading="lazy">
                                @else
                                    <span class="lrow__thumb"></span>
                                @endif
                                <span>
                                    <span class="lrow__name">{{ $itp->title }}</span>
                                    <span class="lrow__meta">{{ $meta }}</span>
                                </span>
                                <span class="lrow__side">3 min</span>
                            </a>
                        @endforeach
                    </div>

                    <x-button variant="ghost" href="{{ route('itps.create', absolute: false) }}" meta="about ten minutes">Make a new personal ITP</x-button>
                @endif
                @break

            @case('primers')
                <p class="tabnote">For a moment, not a day. Breathe 11s and Quantum Whys aren’t here — they belong to the ITP you’re living with.</p>

                <div class="library-list">
                    @foreach ($primerRows as $primer)
                        @if ($primer['itp'] !== null)
                            <a href="{{ route('universals.show', $primer['itp'], absolute: false) }}" data-transition="forward" class="lrow lrow--plain" wire:key="primer-{{ $loop->index }}">
                                <span><span class="lrow__name">{{ $primer['name'] }}</span><span class="lrow__meta">{{ $primer['meta'] }}</span></span>
                                <span class="lrow__side"></span>
                            </a>
                        @else
                            <div class="lrow lrow--plain" aria-disabled="true" wire:key="primer-{{ $loop->index }}">
                                <span><span class="lrow__name">{{ $primer['name'] }}</span><span class="lrow__meta">{{ $primer['meta'] }}</span></span>
                                <span class="lrow__side"></span>
                            </div>
                        @endif
                    @endforeach
                </div>
                @break

            @default
                <p class="tabnote">{{ $universalNote }}</p>

                <div class="library-list">
                    @foreach ($universals as $universal)
                        @php($isLocked = $user === null && ! $universal->is_preview)
                        @php($thumbUrl = $media->url($universal->image_path))
                        <a href="{{ route('universals.show', $universal, absolute: false) }}" data-transition="forward" @class(['lrow', 'lrow--locked' => $isLocked]) wire:key="universal-{{ $universal->id }}">
                            @if ($thumbUrl !== null)
                                <img src="{{ $thumbUrl }}" alt="" class="lrow__thumb" loading="lazy">
                            @else
                                <span class="lrow__thumb"></span>
                            @endif
                            <span>
                                <span class="lrow__name">{{ $universal->display_name }}</span>
                                <span class="lrow__meta">{{ $universal->title }}</span>
                            </span>
                            <span class="lrow__side">{{ $isLocked ? 'sign in' : '3 min' }}</span>
                        </a>
                    @endforeach
                </div>
        @endswitch

        @if ($downloadsPending)
            <div wire:poll.3s="downloadNext" class="sr-only">Downloading audio…</div>
        @endif
    </div>
</div>
