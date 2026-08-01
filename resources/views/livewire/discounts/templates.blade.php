<div>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-900 dark:text-white">Discount Templates</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Pre-approved discounts cashiers can apply from the terminal without manager sign-off (unless flagged below).</p>
        </div>
        <button wire:click="create" type="button" class="rounded-md bg-sky-600 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-700">
            + New template
        </button>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-800">
                <tr>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Name</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Value</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Cashier limit</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-700 dark:bg-slate-900">
                @forelse ($templates as $template)
                    <tr wire:key="dt-{{ $template->id }}">
                        <td class="px-4 py-2.5 text-sm font-medium text-slate-800 dark:text-slate-100">{{ $template->name }}</td>
                        <td class="px-4 py-2.5 text-sm tabular-nums text-slate-600 dark:text-slate-300">
                            {{ $template->discount_type === 'percentage' ? $template->value.'%' : number_format($template->value, 2) }}
                        </td>
                        <td class="px-4 py-2.5 text-sm text-slate-500 dark:text-slate-400">
                            @if ($template->requires_approval)
                                <span class="text-amber-600 dark:text-amber-400">Always needs manager</span>
                            @elseif ($template->max_cashier_value !== null)
                                Up to {{ number_format($template->max_cashier_value, 2) }}
                            @else
                                No limit
                            @endif
                        </td>
                        <td class="px-4 py-2.5">
                            <span @class([
                                'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300' => $template->status === 'active',
                                'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' => $template->status !== 'active',
                            ])>
                                {{ ucfirst($template->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5 text-right text-sm">
                            <button wire:click="edit({{ $template->id }})" class="font-medium text-sky-600 hover:text-sky-800 dark:text-sky-400">Edit</button>
                            <button wire:click="toggleStatus({{ $template->id }})" class="ml-3 font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400">
                                {{ $template->status === 'active' ? 'Deactivate' : 'Activate' }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-slate-400">No discount templates yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $templates->links() }}</div>

    <x-drawer :show="$showDrawer" :title="$editing ? 'Edit discount template' : 'New discount template'">
        <form wire:submit="save" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Name</label>
                <input type="text" wire:model="name" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                @error('name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Type</label>
                    <select wire:model="discount_type" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                        <option value="percentage">Percentage</option>
                        <option value="fixed">Fixed amount</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Value</label>
                    <input type="number" step="0.01" min="0" wire:model="value" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                    @error('value') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Max value a cashier can apply</label>
                <input type="number" step="0.01" min="0" wire:model="max_cashier_value" placeholder="No limit" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                <p class="mt-1 text-xs text-slate-400">Leave blank for no per-template ceiling — the global cashier threshold in Settings still applies to manual discounts, not this template.</p>
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input type="checkbox" wire:model="requires_approval" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                Always requires manager approval, regardless of value
            </label>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Status</label>
                <select wire:model="status" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
        </form>

        <x-slot:footer>
            <button wire:click="closeDrawer" type="button" class="rounded-md px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700">Cancel</button>
            <button wire:click="save" type="button" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">Save</button>
        </x-slot:footer>
    </x-drawer>
</div>
