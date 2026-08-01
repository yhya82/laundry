<div>
    <aside
        class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-(--color-sidebar) transition-transform lg:static lg:translate-x-0"
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    >
        <div class="flex h-16 shrink-0 items-center gap-3 border-b border-(--color-sidebar-border) px-5">
            <div class="flex h-8 w-8 items-center justify-center rounded-md bg-(--color-sidebar-accent) text-sm font-bold text-(--color-sidebar)">
                {{ mb_substr($businessName, 0, 1) }}
            </div>
            <span wire:poll.60s="refreshBranding" class="truncate text-sm font-semibold text-(--color-sidebar-text-active)">
                {{ $businessName }}
            </span>
        </div>

        <nav class="flex-1 space-y-0.5 overflow-y-auto px-3 py-4">
            @foreach ($navItems as $item)
                @php $isActive = request()->routeIs($item['route']); @endphp
                <a
                    href="{{ \Illuminate\Support\Facades\Route::has($item['route']) ? route($item['route']) : '#' }}"
                    @class([
                        'group flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                        'bg-(--color-sidebar-active) text-(--color-sidebar-text-active)' => $isActive,
                        'text-(--color-sidebar-text) hover:bg-(--color-sidebar-active) hover:text-(--color-sidebar-text-active)' => ! $isActive,
                    ])
                >
                    <span @class(['text-(--color-sidebar-accent)' => $isActive])>
                        <x-icon :name="$item['icon']" class="h-5 w-5" />
                    </span>
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="border-t border-(--color-sidebar-border) p-3">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="group flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-(--color-sidebar-text) hover:bg-(--color-sidebar-active) hover:text-(--color-sidebar-text-active)">
                    <x-icon name="logout" class="h-5 w-5" />
                    Log out
                </button>
            </form>
        </div>
    </aside>

    <div
        x-show="sidebarOpen"
        x-cloak
        @click="sidebarOpen = false"
        class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden"
    ></div>
</div>
