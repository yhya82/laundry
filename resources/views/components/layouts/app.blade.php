<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? config('app.name', 'Laundry') }}</title>

        <script>
            (function () {
                var stored = localStorage.getItem('theme');
                var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (stored === 'dark' || (stored !== 'light' && prefersDark)) {
                    document.documentElement.classList.add('dark');
                }
            })();
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="h-full bg-stone-50 dark:bg-slate-950" x-data="{ sidebarOpen: false }">
        <div class="flex h-full">
            <livewire:layout.sidebar />

            <div class="flex min-w-0 flex-1 flex-col">
                <livewire:layout.top-nav />

                <main class="flex-1 overflow-y-auto p-4 sm:p-6">
                    {{ $slot }}
                </main>
            </div>
        </div>

        <x-toast />

        @livewireScripts
    </body>
</html>
