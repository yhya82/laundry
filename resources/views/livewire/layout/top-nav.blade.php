<header class="sticky top-0 z-20 flex h-16 shrink-0 items-center justify-between border-b border-slate-200 bg-white/80 px-4 backdrop-blur dark:border-slate-700 dark:bg-slate-900/80 sm:px-6">
    <button
        type="button"
        @click="sidebarOpen = !sidebarOpen"
        class="flex h-9 w-9 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 lg:hidden"
        aria-label="Toggle navigation"
    >
        <x-icon name="menu" class="h-5 w-5" />
    </button>

    <div class="hidden lg:block"></div>

    <div class="flex items-center gap-2">
        {{-- Theme toggle: reads/writes localStorage directly (no per-user DB
             column in the schema for this — see IMPLEMENTATION_PLAN.md Phase 3). --}}
        <button
            type="button"
            x-data
            @click="
                const isDark = document.documentElement.classList.toggle('dark');
                localStorage.setItem('theme', isDark ? 'dark' : 'light');
            "
            class="flex h-9 w-9 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200"
            aria-label="Toggle theme"
        >
            <x-icon name="sun" class="h-5 w-5 dark:hidden" />
            <x-icon name="moon" class="hidden h-5 w-5 dark:block" />
        </button>

        <livewire:layout.notification-center />

        <div x-data="{ open: false }" class="relative ml-1">
            <button
                type="button"
                @click="open = !open"
                @click.outside="open = false"
                class="flex items-center gap-2 rounded-full py-1 pl-1 pr-2 hover:bg-slate-100 dark:hover:bg-slate-800"
            >
                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-sky-100 text-xs font-semibold text-sky-700 dark:bg-sky-900 dark:text-sky-300">
                    {{ mb_substr(auth()->user()->name, 0, 1) }}
                </span>
                <span class="hidden text-sm font-medium text-slate-700 dark:text-slate-200 sm:block">{{ auth()->user()->name }}</span>
                <x-icon name="chevron-down" class="h-4 w-4 text-slate-400" />
            </button>

            <div
                x-show="open"
                x-cloak
                x-transition
                class="absolute right-0 z-50 mt-2 w-48 rounded-lg border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-800"
            >
                <div class="border-b border-slate-100 px-4 py-2 dark:border-slate-700">
                    <p class="truncate text-sm font-medium text-slate-700 dark:text-slate-200">{{ auth()->user()->name }}</p>
                    <p class="truncate text-xs text-slate-400">{{ auth()->user()->email }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700">
                        <x-icon name="logout" class="h-4 w-4" />
                        Log out
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
