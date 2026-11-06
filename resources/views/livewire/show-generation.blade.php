@php
    use App\Api\GenerationStatus;

    $segments = $generation['attunement']['segments'] ?? [];
    $input = 'rounded-lg border border-stone-300 bg-white px-3 py-2.5 focus:border-stone-500 focus:outline-none focus:ring-2 focus:ring-stone-200';
    $apiErrors = collect($errors->getMessages())->except(['user_explanation', 'requested_change'])->flatten();
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
    @elseif ($isPolling)
        <div wire:poll.3s="refresh" class="mt-6">
            <h1 class="text-2xl font-semibold">We’re working on it</h1>
            <p class="mt-2 text-stone-600">{{ $status?->workingText() ?? 'Working on it…' }}</p>
            <p class="mt-1 text-sm text-stone-500">You can leave this screen. We’ll keep going.</p>

            @if ($status === GenerationStatus::Regenerating && $segments !== [])
                <div class="mt-8 flex flex-col gap-4">
                    @foreach ($segments as $segment)
                        <p wire:key="segment-{{ $segment['id'] }}" @class([
                            'leading-relaxed',
                            'rounded-lg bg-stone-100 px-3 py-2 text-stone-400' => $segment['regenerating'] ?? false,
                        ])>
                            {{ $segment['text'] }}
                            @if ($segment['regenerating'] ?? false)
                                <span class="mt-1 block text-sm italic text-stone-500">Being rewritten…</span>
                            @endif
                        </p>
                    @endforeach
                </div>
            @endif
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
    @elseif ($status === GenerationStatus::AwaitingResonance)
        <h1 class="mt-6 text-2xl font-semibold">Your attunement</h1>
        <p class="mt-2 text-stone-600">Read it slowly. If a part doesn’t feel true for you, say so and we’ll rewrite it.</p>

        <div class="mt-8 flex flex-col gap-6">
            @foreach ($segments as $segment)
                <div wire:key="segment-{{ $segment['id'] }}">
                    <p class="leading-relaxed">{{ $segment['text'] }}</p>

                    @if ($flaggingSegmentId === $segment['id'])
                        <form wire:submit="flag" class="mt-3 flex flex-col gap-3 rounded-xl bg-white p-4 ring-1 ring-stone-200">
                            <div class="flex flex-col gap-1.5">
                                <label for="user_explanation" class="text-sm font-medium">What doesn’t resonate?</label>
                                <textarea wire:model="user_explanation" id="user_explanation" rows="3" maxlength="2000" required class="{{ $input }}"></textarea>
                                @error('user_explanation') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label for="requested_change" class="text-sm font-medium">What would feel true instead? <span class="font-normal text-stone-500">(optional)</span></label>
                                <textarea wire:model="requested_change" id="requested_change" rows="2" maxlength="2000" class="{{ $input }}"></textarea>
                                @error('requested_change') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="flex gap-3">
                                <button type="submit" class="flex-1 rounded-lg bg-stone-900 px-4 py-2.5 font-medium text-white disabled:opacity-50" wire:loading.attr="disabled">Rewrite this part</button>
                                <button type="button" wire:click="cancelFlag" class="rounded-lg px-4 py-2.5 text-stone-600 ring-1 ring-stone-300">Cancel</button>
                            </div>
                        </form>
                    @else
                        <button type="button" wire:click="startFlag({{ $segment['id'] }})" class="mt-1 text-sm text-stone-500 underline underline-offset-2">
                            This doesn’t resonate
                        </button>
                    @endif
                </div>
            @endforeach
        </div>

        <button type="button" wire:click="accept" class="mt-10 w-full rounded-lg bg-stone-900 px-4 py-2.5 font-medium text-white disabled:opacity-50" wire:loading.attr="disabled">
            Accept
        </button>
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
