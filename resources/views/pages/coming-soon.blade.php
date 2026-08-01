<x-layouts.app :title="$title">
    <div class="mx-auto flex max-w-lg flex-col items-center py-16 text-center">
        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-sky-100 text-sky-600 dark:bg-sky-900 dark:text-sky-300">
            <x-icon name="cog" class="h-6 w-6" />
        </div>
        <h1 class="mt-4 text-lg font-semibold text-slate-900 dark:text-white">{{ $title }}</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $description }}</p>
        <p class="mt-1 text-xs text-slate-400">You can see this page because your role has the <code class="rounded bg-slate-100 px-1 py-0.5 dark:bg-slate-800">{{ $permission }}</code> permission.</p>
    </div>
</x-layouts.app>
