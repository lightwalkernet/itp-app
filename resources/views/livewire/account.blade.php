<div class="px-6 py-10">
    <h1 class="text-2xl font-semibold">Account</h1>

    <dl class="mt-6 divide-y divide-stone-200 rounded-xl bg-white text-sm ring-1 ring-stone-200">
        <div class="flex justify-between gap-4 px-4 py-3">
            <dt class="text-stone-500">Name</dt>
            <dd>{{ $user->first_name }}</dd>
        </div>
        <div class="flex justify-between gap-4 px-4 py-3">
            <dt class="text-stone-500">Email</dt>
            <dd class="truncate">{{ $user->email }}</dd>
        </div>
    </dl>

    <form method="POST" action="{{ route('sign-out') }}" class="mt-6">
        @csrf
        <button type="submit" class="w-full rounded-lg bg-white px-4 py-2.5 font-medium ring-1 ring-stone-300">Sign out</button>
    </form>
</div>
