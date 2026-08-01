<div class="mx-auto max-w-3xl">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-white">Welcome back, {{ auth()->user()->name }}</h1>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        This is the platform shell built in Phase 3 — real dashboard cards, charts, and activity land in Phase 13.
    </p>

    <div class="mt-6 rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
        <p class="font-medium text-slate-800 dark:text-slate-100">Your access</p>
        <p class="mt-1">
            Roles: {{ auth()->user()->roles->pluck('name')->join(', ') ?: 'None assigned' }}
        </p>
        <p class="mt-1">
            Permissions: {{ count(auth()->user()->permissionSlugs()) }} granted
        </p>
    </div>
</div>
