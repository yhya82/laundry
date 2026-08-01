<div>
    <div class="mb-3 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Addresses</h3>
        @can('customers.update')
            <button wire:click="create" type="button" class="text-sm font-medium text-sky-600 hover:text-sky-800 dark:text-sky-400">+ Add address</button>
        @endcan
    </div>

    <div class="space-y-2">
        @forelse ($addresses as $address)
            <div wire:key="addr-{{ $address->id }}" class="flex items-start justify-between rounded-md border border-slate-200 p-3 text-sm dark:border-slate-700">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="font-medium text-slate-800 dark:text-slate-100">{{ $address->label ?: 'Address' }}</span>
                        @if ($address->is_default)
                            <span class="inline-flex rounded-full bg-sky-100 px-2 py-0.5 text-xs font-medium text-sky-700 dark:bg-sky-900 dark:text-sky-300">Default</span>
                        @endif
                    </div>
                    <p class="mt-0.5 text-slate-500 dark:text-slate-400">
                        {{ collect([$address->street, $address->area, $address->city])->filter()->join(', ') ?: '—' }}
                    </p>
                    @if ($address->notes)
                        <p class="mt-0.5 text-xs text-slate-400">{{ $address->notes }}</p>
                    @endif
                </div>
                @can('customers.update')
                    <div class="flex shrink-0 gap-3">
                        <button wire:click="edit({{ $address->id }})" class="text-xs font-medium text-sky-600 hover:text-sky-800 dark:text-sky-400">Edit</button>
                        <button wire:click="delete({{ $address->id }})" wire:confirm="Remove this address?" class="text-xs font-medium text-rose-600 hover:text-rose-800 dark:text-rose-400">Remove</button>
                    </div>
                @endcan
            </div>
        @empty
            <p class="text-sm text-slate-400">No addresses on file.</p>
        @endforelse
    </div>

    <x-drawer :show="$showDrawer" :title="$editing ? 'Edit address' : 'New address'">
        <form wire:submit="save" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Label</label>
                <input type="text" wire:model="label" placeholder="Home, Office..." class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Street</label>
                <input type="text" wire:model="street" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Area</label>
                    <input type="text" wire:model="area" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">City</label>
                    <input type="text" wire:model="city" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Notes</label>
                <input type="text" wire:model="notes" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input type="checkbox" wire:model="is_default" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                Set as default address
            </label>
        </form>

        <x-slot:footer>
            <button wire:click="closeDrawer" type="button" class="rounded-md px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700">Cancel</button>
            <button wire:click="save" type="button" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">Save</button>
        </x-slot:footer>
    </x-drawer>
</div>
