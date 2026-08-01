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
        <div class="flex items-center justify-between px-4 py-1.5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Notifications</p>
            @if ($unreadCount > 0)
                <button wire:click="markAllAsRead" type="button" class="text-xs font-medium text-sky-600 hover:underline dark:text-sky-400">Mark all read</button>
            @endif
        </div>

        @forelse ($notifications as $notification)
            <button
                wire:click="markAsRead({{ $notification->id }})"
                type="button"
                @class([
                    'block w-full px-4 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-700/50',
                    'bg-sky-50 dark:bg-sky-950/40' => ! $notification->read_at,
                ])
            >
                <p class="font-medium text-slate-800 dark:text-slate-100">{{ $notification->title }}</p>
                <p class="text-slate-500 dark:text-slate-400">{{ $notification->message }}</p>
                <p class="mt-0.5 text-xs text-slate-400">{{ $notification->created_at->diffForHumans() }}</p>
            </button>
        @empty
            <p class="px-4 py-6 text-center text-sm text-slate-400">No notifications yet.</p>
        @endforelse

        <div class="mt-1 border-t border-slate-100 px-4 pt-2 dark:border-slate-700">
            <a href="{{ route('notifications.preferences') }}" wire:navigate class="text-xs font-medium text-sky-600 hover:underline dark:text-sky-400">Notification preferences</a>
        </div>
    </div>
</div>
