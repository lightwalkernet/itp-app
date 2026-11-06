@php
    /*
     * The intake questions, in order. Wording follows the old harness's IntakeForm, written for
     * the person rather than the tester.
     */
    $questions = [
        'q1_dedication' => ['label' => 'What are you dedicating this ITP to?', 'help' => 'The area of your life, and the concrete outcome you want there. Names and specifics help.', 'long' => true],
        'q2_challenge' => ['label' => 'What challenge stands in the way?', 'help' => 'What makes this hard right now.', 'long' => true],
        'q3_old_story' => ['label' => 'What is the old story?', 'help' => 'The belief or story you have been telling yourself about this.', 'long' => true],
        'q3_phrase' => ['label' => 'Is there a phrase that sums it up?', 'help' => 'The single golden phrase of that old story, if one comes to mind.', 'optional' => true],
        'q3_protective' => ['label' => 'What has that story protected?', 'help' => 'What the belief protected you from, or made possible.', 'optional' => true],
        'q4_vision' => ['label' => 'What is your vision?', 'help' => 'How it looks and feels when the outcome is real.', 'long' => true],
        'q4_scene' => ['label' => 'A scene or a sign', 'help' => 'One specific moment that would show you it has happened.', 'optional' => true],
        'q5_soulful' => ['label' => 'What makes this soulful for you?', 'help' => 'Why it matters, beyond the outcome itself.', 'long' => true],
        'avoid' => ['label' => 'Anything to leave out?', 'help' => 'Words, ideas or images you don’t want in your ITP.', 'optional' => true],
    ];
    $input = 'rounded-lg border border-stone-300 bg-white px-3 py-2.5 focus:border-stone-500 focus:outline-none focus:ring-2 focus:ring-stone-200';
@endphp

<div class="px-6 py-10" data-swipe-back data-back-url="{{ route('home', absolute: false) }}">
    <a href="{{ route('home', absolute: false) }}" data-transition="back" class="text-sm text-stone-500">Back</a>

    <h1 class="mt-4 text-2xl font-semibold">Create a personal ITP</h1>
    <p class="mt-2 text-stone-600">Answer in your own words. There are no wrong answers.</p>

    <form wire:submit="submit" class="mt-8 flex flex-col gap-6">
        <div class="flex flex-col gap-1.5">
            <label for="display_name" class="text-sm font-medium">Your name</label>
            <p class="text-sm text-stone-500">The name your ITP speaks to you by.</p>
            <input wire:model="display_name" id="display_name" autocomplete="given-name" required class="{{ $input }}">
            @error('display_name') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <fieldset class="flex flex-col gap-1.5">
            <legend class="text-sm font-medium">How should it address you?</legend>
            <label class="mt-1.5 flex items-center gap-2">
                <input type="radio" wire:model="address_style" value="beloved_name" class="accent-stone-900">
                Beloved {{ $display_name !== '' ? $display_name : 'and your name' }}
            </label>
            <label class="flex items-center gap-2">
                <input type="radio" wire:model="address_style" value="beloved_one" class="accent-stone-900">
                Beloved One
            </label>
            @error('address_style') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </fieldset>

        @foreach ($questions as $field => $question)
            <div class="flex flex-col gap-1.5" wire:key="question-{{ $field }}">
                <label for="{{ $field }}" class="text-sm font-medium">
                    {{ $question['label'] }}
                    @if ($question['optional'] ?? false)
                        <span class="font-normal text-stone-500">(optional)</span>
                    @endif
                </label>
                <p class="text-sm text-stone-500">{{ $question['help'] }}</p>
                @if ($question['long'] ?? false)
                    <textarea wire:model="{{ $field }}" id="{{ $field }}" rows="4" required class="{{ $input }}"></textarea>
                @else
                    <input wire:model="{{ $field }}" id="{{ $field }}" class="{{ $input }}">
                @endif
                @error($field) <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        @endforeach

        @error('connection')
            <p class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200">
                {{ $isMobile ? 'Creating an ITP needs a connection. Check that you’re online, then try again. Your answers are still here.' : $message }}
            </p>
        @enderror
        @error('form') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

        <button type="submit" class="rounded-lg bg-stone-900 px-4 py-2.5 font-medium text-white disabled:opacity-50" wire:loading.attr="disabled">
            Create my ITP
        </button>
    </form>
</div>
