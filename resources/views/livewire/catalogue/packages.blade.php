<div>
    <x-tabs :items="[
        'packages.index' => 'Packages',
        'packages.clothing-types' => 'Clothing Types',
        'packages.services' => 'Services',
        'packages.machines' => 'Machines',
    ]" />

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-900 dark:text-white">Packages</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Priced packages with a clothing-item limit and optional attached services.</p>
        </div>
        @can('packages.manage')
            <button wire:click="create" type="button" class="rounded-md bg-sky-600 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-700">
                + New package
            </button>
        @endcan
    </div>

    <div class="mb-4">
        <input
            type="search"
            wire:model.live.debounce.300ms="search"
            placeholder="Search name..."
            class="w-full max-w-xs rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
        >
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-800">
                <tr>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Name</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Price</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Clothes limit</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Priority</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Services</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-700 dark:bg-slate-900">
                @forelse ($packages as $package)
                    <tr wire:key="pkg-{{ $package->id }}">
                        <td class="px-4 py-2.5 text-sm font-medium text-slate-800 dark:text-slate-100">{{ $package->name }}</td>
                        <td class="px-4 py-2.5 text-sm tabular-nums text-slate-600 dark:text-slate-300">{{ number_format($package->price, 2) }}</td>
                        <td class="px-4 py-2.5 text-sm tabular-nums text-slate-600 dark:text-slate-300">{{ $package->maximum_clothes }} items</td>
                        <td class="px-4 py-2.5">
                            @if ($package->priority === 'express')
                                <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900 dark:text-amber-300">Express</span>
                            @else
                                <span class="text-sm text-slate-500 dark:text-slate-400">Normal</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-sm text-slate-500 dark:text-slate-400">{{ $package->services_count }}</td>
                        <td class="px-4 py-2.5">
                            <span @class([
                                'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300' => $package->status === 'active',
                                'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' => $package->status !== 'active',
                            ])>
                                {{ ucfirst($package->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5 text-right text-sm">
                            @can('packages.manage')
                                <button wire:click="edit({{ $package->id }})" class="font-medium text-sky-600 hover:text-sky-800 dark:text-sky-400">Edit</button>
                                <button wire:click="toggleStatus({{ $package->id }})" class="ml-3 font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400">
                                    {{ $package->status === 'active' ? 'Deactivate' : 'Activate' }}
                                </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-slate-400">No packages yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $packages->links() }}</div>

    <x-drawer :show="$showDrawer" :title="$editing ? 'Edit package' : 'New package'">
        <form wire:submit="save" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Name</label>
                <input type="text" wire:model="name" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                @error('name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Price</label>
                    <input type="number" step="0.01" min="0" wire:model="price" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                    @error('price') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Max clothes</label>
                    <input type="number" min="1" wire:model="maximum_clothes" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                    @error('maximum_clothes') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-slate-400">Enforced at the database level — the terminal can never add more items to a package instance than this.</p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Priority</label>
                <select wire:model="priority" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                    <option value="normal">Normal</option>
                    <option value="express">Express</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Description</label>
                <textarea wire:model="description" rows="2" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white"></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Attached services</label>
                <div class="mt-2 max-h-40 space-y-1.5 overflow-y-auto rounded-md border border-slate-200 p-2 dark:border-slate-700">
                    @forelse ($this->services as $service)
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                            <input type="checkbox" wire:model="selectedServices" value="{{ $service->id }}" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                            {{ $service->name }}
                        </label>
                    @empty
                        <p class="text-sm text-slate-400">No active services yet.</p>
                    @endforelse
                </div>
            </div>

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
