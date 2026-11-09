<div class="flex min-h-dvh items-center justify-center px-6">
    <div class="w-full max-w-sm">
        <h1 class="text-center text-2xl font-semibold">it's totally possible</h1>

        @if (! $codeSent)
            <form wire:submit="sendCode" class="mt-8 flex flex-col gap-4">
                <div class="flex flex-col gap-1.5">
                    <label for="email" class="text-sm font-medium">Email</label>
                    <input
                        wire:model="email"
                        id="email"
                        type="email"
                        autocomplete="email"
                        inputmode="email"
                        autocapitalize="off"
                        required
                        class="rounded-lg border border-stone-300 bg-white px-3 py-2.5 focus:border-stone-500 focus:outline-none focus:ring-2 focus:ring-stone-200"
                    >
                    @error('email') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="rounded-lg bg-stone-900 px-4 py-2.5 font-medium text-white disabled:opacity-50" wire:loading.attr="disabled">
                    Send me a code
                </button>
            </form>
        @else
            <form wire:submit="verifyCode" class="mt-8 flex flex-col gap-4">
                <p class="text-sm text-stone-600">
                    We sent a six-digit code to <span class="font-medium text-stone-900">{{ $email }}</span>.
                </p>

                <div class="flex flex-col gap-1.5">
                    <label for="code" class="text-sm font-medium">Code</label>
                    <input
                        wire:model="code"
                        id="code"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        maxlength="6"
                        required
                        class="rounded-lg border border-stone-300 bg-white px-3 py-2.5 text-center text-lg tracking-[0.5em] focus:border-stone-500 focus:outline-none focus:ring-2 focus:ring-stone-200"
                    >
                    @error('code') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                @unless (\App\Support\Runtime::isMobile())
                    <label class="flex items-start gap-2 text-sm text-stone-600">
                        <input wire:model="keepSignedIn" type="checkbox" class="mt-0.5 rounded border-stone-300">
                        <span>This is my own device. Keep me signed in for 90 days.</span>
                    </label>
                @endunless

                <button type="submit" class="rounded-lg bg-stone-900 px-4 py-2.5 font-medium text-white disabled:opacity-50" wire:loading.attr="disabled">
                    Sign in
                </button>

                <button type="button" wire:click="useDifferentEmail" class="text-sm text-stone-500">
                    Use a different email
                </button>
            </form>
        @endif
    </div>
</div>
