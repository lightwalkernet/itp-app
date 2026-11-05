<div class="flex min-h-dvh items-center justify-center px-6">
    <form wire:submit="save" class="flex w-full max-w-sm flex-col gap-4">
        <h1 class="text-2xl font-semibold">What should we call you?</h1>

        <div class="flex flex-col gap-1.5">
            <label for="first_name" class="text-sm font-medium">First name</label>
            <input
                wire:model="first_name"
                id="first_name"
                autocomplete="given-name"
                required
                class="rounded-lg border border-stone-300 bg-white px-3 py-2.5 focus:border-stone-500 focus:outline-none focus:ring-2 focus:ring-stone-200"
            >
            @error('first_name') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="rounded-lg bg-stone-900 px-4 py-2.5 font-medium text-white disabled:opacity-50" wire:loading.attr="disabled">
            Continue
        </button>
    </form>
</div>
