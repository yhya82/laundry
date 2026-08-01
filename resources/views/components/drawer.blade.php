@props(['show' => false, 'title' => ''])

@if ($show)
    <div class="fixed inset-0 z-50" x-data x-init="document.body.style.overflow = 'hidden'" x-destroy="document.body.style.overflow = ''">
        <div
            class="absolute inset-0 bg-slate-900/50"
            wire:click="closeDrawer"
            x-transition.opacity
        ></div>

        <div class="absolute inset-y-0 right-0 flex max-w-full pl-10">
            <div
                class="flex h-full w-screen max-w-md flex-col bg-white shadow-xl dark:bg-slate-800"
                x-transition:enter="transition ease-in-out duration-300"
                x-transition:enter-start="translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transition ease-in-out duration-200"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="translate-x-full"
            >
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-white">{{ $title }}</h2>
                    <button
                        type="button"
                        wire:click="closeDrawer"
                        class="flex h-8 w-8 items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700 dark:hover:text-slate-200"
                        aria-label="Close"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto px-5 py-4">
                    {{ $slot }}
                </div>

                @isset($footer)
                    <div class="flex items-center justify-end gap-2 border-t border-slate-200 px-5 py-4 dark:border-slate-700">
                        {{ $footer }}
                    </div>
                @endisset
            </div>
        </div>
    </div>
@endif
