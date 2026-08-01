@props(['items'])

<div class="mb-5 border-b border-slate-200 dark:border-slate-700">
    <nav class="-mb-px flex gap-6">
        @foreach ($items as $route => $label)
            <a
                href="{{ route($route) }}"
                @class([
                    'border-b-2 px-1 py-2.5 text-sm font-medium',
                    'border-sky-600 text-sky-600 dark:text-sky-400' => request()->routeIs($route),
                    'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' => ! request()->routeIs($route),
                ])
            >
                {{ $label }}
            </a>
        @endforeach
    </nav>
</div>
