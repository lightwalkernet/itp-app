@php
    use App\Api\GenerationStatus;

    $input = 'rounded-lg border border-stone-300 bg-white px-3 py-2.5 focus:border-stone-500 focus:outline-none focus:ring-2 focus:ring-stone-200';
    $apiErrors = collect($errors->getMessages())->except(['user_explanation', 'requested_change', 'segment_id', 'section', 'keep', 'accept'])->flatten();
@endphp

<div class="px-6 py-10" data-swipe-back data-back-url="{{ route('home', absolute: false) }}">
    <a href="{{ route('home', absolute: false) }}" data-transition="back" class="text-sm text-stone-500">Home</a>

    @if ($apiErrors->isNotEmpty())
        <div class="mt-6 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200">
            @foreach ($apiErrors as $message)
                <p>{{ $message }}</p>
            @endforeach
        </div>
    @endif

    @if ($generation === [])
        <button type="button" wire:click="refresh" class="mt-6 rounded-lg bg-white px-4 py-2.5 font-medium ring-1 ring-stone-300">Try again</button>
    @elseif ($status === GenerationStatus::AwaitingResonance || $status === GenerationStatus::Regenerating)
        <div @if ($isPolling) wire:poll.3s="refresh" @endif class="mt-6">
            <h1 class="text-2xl font-semibold">Your attunement</h1>
            <p class="mt-2 text-stone-600">Read it slowly. If a part doesn’t feel true for you, say so and we’ll rewrite it.</p>

            <div class="mt-8 flex flex-col gap-8">
                @foreach ($sections as $section)
                    @php
                        $part = $section['part'];
                        $proposal = $section['proposal'] ?? null;
                        $sectionErrors = in_array($part, [$flaggingPart, $choosingPart], true)
                            ? [...$errors->get('section'), ...$errors->get('keep')]
                            : [];
                    @endphp

                    <section wire:key="section-{{ $part }}">
                        @if ($section['regenerating'] ?? false)
                            <div class="flex flex-col gap-2 rounded-lg bg-stone-100 px-3 py-2 text-stone-400">
                                @foreach ($section['segments'] as $segment)
                                    <p wire:key="segment-{{ $segment['id'] }}" class="leading-relaxed">{{ $segment['text'] }}</p>
                                @endforeach
                                <p class="text-sm italic text-stone-500">Rewriting this part…</p>
                            </div>
                        @elseif ($proposal !== null)
                            <div class="flex flex-col gap-5 rounded-xl bg-white p-4 ring-1 ring-stone-200">
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wide text-stone-500">New version</p>
                                    <div class="mt-2 flex flex-col gap-2">
                                        @foreach ($proposal['segments'] as $segment)
                                            <p wire:key="proposed-{{ $segment['id'] }}" class="leading-relaxed">{{ $segment['text'] }}</p>
                                        @endforeach
                                    </div>
                                    <button type="button" wire:click="choose({{ $part }}, 'new')" class="mt-3 w-full rounded-lg bg-stone-900 px-4 py-2.5 font-medium text-white disabled:opacity-50" wire:loading.attr="disabled">
                                        Use this
                                    </button>
                                </div>

                                <div class="border-t border-stone-200 pt-5">
                                    <p class="text-xs font-medium uppercase tracking-wide text-stone-500">Your original</p>
                                    <div class="mt-2 flex flex-col gap-2 text-stone-600">
                                        @foreach ($section['segments'] as $segment)
                                            <p wire:key="segment-{{ $segment['id'] }}" class="leading-relaxed">{{ $segment['text'] }}</p>
                                        @endforeach
                                    </div>
                                    <button type="button" wire:click="choose({{ $part }}, 'original')" class="mt-3 w-full rounded-lg bg-white px-4 py-2.5 font-medium ring-1 ring-stone-300 disabled:opacity-50" wire:loading.attr="disabled">
                                        Keep the original
                                    </button>
                                </div>
                            </div>
                        @else
                            <div class="flex flex-col gap-2">
                                @foreach ($section['segments'] as $segment)
                                    <p wire:key="segment-{{ $segment['id'] }}" class="leading-relaxed">{{ $segment['text'] }}</p>
                                @endforeach
                            </div>

                            @if ($flaggingPart === $part)
                                <form wire:submit="flag" class="mt-3 flex flex-col gap-3 rounded-xl bg-white p-4 ring-1 ring-stone-200">
                                    <div class="flex flex-col gap-1.5">
                                        <label for="user_explanation" class="text-sm font-medium">What doesn’t resonate?</label>
                                        <textarea wire:model="user_explanation" id="user_explanation" rows="3" maxlength="2000" required class="{{ $input }}"></textarea>
                                        @error('user_explanation') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="flex flex-col gap-1.5">
                                        <label for="requested_change" class="text-sm font-medium">What would you prefer? <span class="font-normal text-stone-500">(optional)</span></label>
                                        <textarea wire:model="requested_change" id="requested_change" rows="2" maxlength="2000" class="{{ $input }}"></textarea>
                                        @error('requested_change') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    @if (count($section['segments']) > 1)
                                        <fieldset class="flex flex-col gap-2">
                                            <legend class="text-sm font-medium">Which line bothered you most? <span class="font-normal text-stone-500">(optional)</span></legend>
                                            @foreach ($section['segments'] as $segment)
                                                <label wire:key="pinpoint-{{ $segment['id'] }}" class="mt-1 flex items-start gap-2 text-sm text-stone-700">
                                                    <input type="radio" wire:model="segment_id" name="segment_id" value="{{ $segment['id'] }}" class="mt-1">
                                                    <span>{{ $segment['text'] }}</span>
                                                </label>
                                            @endforeach
                                            @if ($segment_id !== null)
                                                <button type="button" wire:click="$set('segment_id', null)" class="self-start text-sm text-stone-500 underline underline-offset-2">No line in particular</button>
                                            @endif
                                            @error('segment_id') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                                        </fieldset>
                                    @endif
                                    <div class="flex gap-3">
                                        <button type="submit" class="flex-1 rounded-lg bg-stone-900 px-4 py-2.5 font-medium text-white disabled:opacity-50" wire:loading.attr="disabled">Rewrite this part</button>
                                        <button type="button" wire:click="cancelFlag" class="rounded-lg px-4 py-2.5 text-stone-600 ring-1 ring-stone-300">Cancel</button>
                                    </div>
                                </form>
                            @elseif ($status === GenerationStatus::AwaitingResonance)
                                <button type="button" wire:click="startFlag({{ $part }})" class="mt-2 text-sm text-stone-500 underline underline-offset-2">
                                    This doesn’t resonate
                                </button>
                            @endif
                        @endif

                        @foreach ($sectionErrors as $message)
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @endforeach
                    </section>
                @endforeach
            </div>

            @if ($awaitsChoice)
                <p class="mt-10 text-center text-sm text-stone-500">Choose a version for the rewritten part first, then you can accept your attunement.</p>
            @elseif ($isRewriting || $status === GenerationStatus::Regenerating)
                <p class="mt-10 text-center text-sm text-stone-500">We’re rewriting a part for you. You can accept once it’s ready.</p>
            @else
                @error('accept') <p class="mt-10 text-sm text-red-600">{{ $message }}</p> @enderror
                <button type="button" wire:click="accept" @class(['w-full rounded-lg bg-stone-900 px-4 py-2.5 font-medium text-white disabled:opacity-50', 'mt-10' => ! $errors->has('accept'), 'mt-3' => $errors->has('accept')]) wire:loading.attr="disabled">
                    Accept
                </button>
            @endif
        </div>
    @elseif ($isPolling)
        <div wire:poll.3s="refresh" class="mt-6">
            <h1 class="text-2xl font-semibold">We’re working on it</h1>
            <p class="mt-2 text-stone-600">{{ $status?->workingText() ?? 'Working on it…' }}</p>
            <p class="mt-1 text-sm text-stone-500">You can leave this screen. We’ll keep going.</p>
        </div>
    @elseif ($status === GenerationStatus::AwaitingFollowup)
        <h1 class="mt-6 text-2xl font-semibold">A few more questions</h1>
        <p class="mt-2 text-stone-600">These help your ITP speak to exactly where you are. Answer what you can.</p>

        <form wire:submit="answerFollowups" class="mt-8 flex flex-col gap-6">
            @foreach ($generation['followups'] ?? [] as $followup)
                <div class="flex flex-col gap-1.5" wire:key="followup-{{ $followup['id'] }}">
                    <label for="answer-{{ $followup['id'] }}" class="font-medium">{{ $followup['question_text'] }}</label>
                    <textarea wire:model="answers.{{ $followup['id'] }}" id="answer-{{ $followup['id'] }}" rows="3" class="{{ $input }}"></textarea>
                </div>
            @endforeach

            <button type="submit" class="rounded-lg bg-stone-900 px-4 py-2.5 font-medium text-white disabled:opacity-50" wire:loading.attr="disabled">
                Send my answers
            </button>
        </form>
    @elseif ($status === GenerationStatus::Complete)
        @if ($itpIsHere)
            <h1 class="mt-6 text-2xl font-semibold">Your ITP is ready</h1>
            @isset($generation['title'])
                <p class="mt-2 text-stone-600">{{ $generation['title'] }}</p>
            @endisset
            <a href="{{ route('itps.show', $generation['itp_id'], absolute: false) }}" data-transition="forward" class="mt-6 block rounded-lg bg-stone-900 px-4 py-2.5 text-center font-medium text-white">
                Open my ITP
            </a>
        @else
            <h1 class="mt-6 text-2xl font-semibold">Your ITP is ready</h1>
            <p class="mt-2 text-stone-600">It isn’t on this device yet. Check that you’re online, then refresh.</p>
            <button type="button" wire:click="refresh" class="mt-6 rounded-lg bg-white px-4 py-2.5 font-medium ring-1 ring-stone-300 disabled:opacity-50" wire:loading.attr="disabled">
                Refresh
            </button>
        @endif
    @elseif ($status?->isStopped())
        <h1 class="mt-6 text-2xl font-semibold">{{ match ($status) {
            GenerationStatus::Deferred => 'We’ve paused here',
            GenerationStatus::Blocked => 'We can’t create this ITP',
            default => 'Something went wrong',
        } }}</h1>
        <p class="mt-2 text-stone-600">{{ $generation['message'] ?? 'This ITP couldn’t be created.' }}</p>
    @endif
</div>
