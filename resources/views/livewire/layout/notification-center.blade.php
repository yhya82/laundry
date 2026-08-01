<div x-data="{ open: false }" class="relative">
    <button
        type="button"
        @click="open = !open"
        @click.outside="open = false"
        class="relative flex h-9 w-9 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200"
        aria-label="Notifications"
    >
        <x-icon name="bell" class="h-5 w-5" />
        @if ($unreadCount > 0)
            <span class="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-semibold leading-none text-white">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition
        class="absolute right-0 z-50 mt-2 w-80 rounded-lg border border-slate-200 bg-white py-2 shadow-lg dark:border-slate-700 dark:bg-slate-800"
    >
        <p class="px-4 py-1.5 text-xs font-semibold uppercase tracking-wide text-slate-400">Notifications</p>

        @forelse ($notifications as $notification)
            <div class="px-4 py-2 text-sm text-slate-700 dark:text-slate-200">
                <p class="font-medium">{{ $notification->title }}</p>
                <p class="text-slate-500 dark:text-slate-400">{{ $notification->message }}</p>
            </div>
        @empty
            <p class="px-4 py-6 text-center text-sm text-slate-400">No notifications yet.</p>
        @endforelse
    </div>
</div>
