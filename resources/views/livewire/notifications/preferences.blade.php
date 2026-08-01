<div class="mx-auto max-w-lg">
    <div class="mb-5">
        <h1 class="text-lg font-semibold text-slate-900 dark:text-white">Notification Preferences</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Choose which channels you're notified on. This only affects your own account.</p>
    </div>

    <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
        <div class="space-y-3">
            @foreach ($channels as $channel => $enabled)
                <label class="flex items-center justify-between rounded-md border border-slate-100 px-3 py-2.5 dark:border-slate-800">
                    <span class="text-sm font-medium capitalize text-slate-700 dark:text-slate-200">{{ str_replace('_', ' ', $channel) }}</span>
                    <input type="checkbox" wire:model="channels.{{ $channel }}" class="rounded border-slate-300 dark:border-slate-600">
                </label>
            @endforeach
        </div>

        <button wire:click="save" type="button" class="mt-4 w-full rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">
            Save preferences
        </button>
    </div>
</div>
