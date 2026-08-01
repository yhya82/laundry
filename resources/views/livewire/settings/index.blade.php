<div class="mx-auto max-w-3xl">
    <div class="mb-6">
        <h1 class="text-lg font-semibold text-slate-900 dark:text-white">Settings</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Changes propagate live across every open session — no refresh needed.</p>
    </div>

    <div class="space-y-8">
        @foreach ($grouped as $group => $items)
            <div>
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-400">{{ ucfirst($group) }}</h2>
                <div class="space-y-4 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
                    @foreach ($items as $item)
                        <div class="flex items-start justify-between gap-4 border-b border-slate-100 pb-4 last:border-0 last:pb-0 dark:border-slate-700">
                            <div class="min-w-0 flex-1">
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                                    {{ ucfirst(str_replace('_', ' ', $item['setting_key'])) }}
                                </label>
                                @if ($item['description'])
                                    <p class="mt-0.5 text-xs text-slate-400">{{ $item['description'] }}</p>
                                @endif

                                @if ($item['value_type'] === 'boolean')
                                    <select wire:model="values.{{ $item['id'] }}" class="mt-2 block w-full max-w-xs rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                                        <option value="true">Enabled</option>
                                        <option value="false">Disabled</option>
                                    </select>
                                @elseif ($item['value_type'] === 'integer')
                                    <input type="number" wire:model="values.{{ $item['id'] }}" class="mt-2 block w-full max-w-xs rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                                @elseif ($item['value_type'] === 'json')
                                    <textarea wire:model="values.{{ $item['id'] }}" rows="3" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 font-mono text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white"></textarea>
                                @else
                                    <input type="text" wire:model="values.{{ $item['id'] }}" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                                @endif
                                @error("values.{$item['id']}") <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                            </div>

                            @can('settings.manage')
                                <button
                                    wire:click="save({{ $item['id'] }})"
                                    type="button"
                                    class="mt-6 shrink-0 rounded-md bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-700"
                                >
                                    Save
                                </button>
                            @endcan
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
