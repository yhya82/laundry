<div>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-900 dark:text-white">Customers</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Walk-in and subscription customers.</p>
        </div>
        @can('customers.create')
            <button wire:click="create" type="button" class="rounded-md bg-sky-600 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-700">
                + New customer
            </button>
        @endcan
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <input
            type="search"
            wire:model.live.debounce.300ms="search"
            placeholder="Search name or phone..."
            class="w-full max-w-xs rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
        >
        <select wire:model.live="typeFilter" class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white">
            <option value="">All types</option>
            <option value="walk_in">Walk-in</option>
            <option value="subscription">Subscription</option>
        </select>
        <select wire:model.live="statusFilter" class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white">
            <option value="">All statuses</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="suspended">Suspended</option>
        </select>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-800">
                <tr>
                    @foreach (['name' => 'Name', 'phone' => 'Phone', 'customer_type' => 'Type', 'status' => 'Status', 'created_at' => 'Joined'] as $col => $label)
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <button wire:click="sort('{{ $col }}')" type="button" class="flex items-center gap-1 hover:text-slate-800 dark:hover:text-slate-200">
                                {{ $label }}
                                @if ($sortBy === $col)
                                    <span class="text-sky-500">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </button>
                        </th>
                    @endforeach
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-700 dark:bg-slate-900">
                @forelse ($customers as $customer)
                    <tr wire:key="cust-{{ $customer->id }}">
                        <td class="px-4 py-2.5 text-sm font-medium text-slate-800 dark:text-slate-100">
                            <a href="{{ route('customers.show', $customer) }}" class="hover:text-sky-600 dark:hover:text-sky-400">{{ $customer->name }}</a>
                        </td>
                        <td class="px-4 py-2.5 text-sm tabular-nums text-slate-600 dark:text-slate-300">{{ $customer->phone }}</td>
                        <td class="px-4 py-2.5 text-sm text-slate-500 dark:text-slate-400">{{ $customer->customer_type === 'walk_in' ? 'Walk-in' : 'Subscription' }}</td>
                        <td class="px-4 py-2.5">
                            <span @class([
                                'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300' => $customer->status === 'active',
                                'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' => $customer->status !== 'active',
                            ])>
                                {{ ucfirst($customer->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5 text-sm text-slate-500 dark:text-slate-400">{{ $customer->created_at->format('M j, Y') }}</td>
                        <td class="px-4 py-2.5 text-right text-sm">
                            <a href="{{ route('customers.show', $customer) }}" class="font-medium text-sky-600 hover:text-sky-800 dark:text-sky-400">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-slate-400">No customers yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $customers->links() }}</div>

    <x-drawer :show="$showDrawer" title="New customer">
        <form wire:submit="save" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Name</label>
                <input type="text" wire:model="name" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                @error('name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Phone</label>
                <input type="text" wire:model="phone" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                @error('phone') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Email</label>
                <input type="email" wire:model="email" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                @error('email') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Customer type</label>
                <select wire:model="customer_type" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                    <option value="walk_in">Walk-in</option>
                    <option value="subscription">Subscription</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Status</label>
                <select wire:model="status" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="suspended">Suspended</option>
                </select>
            </div>
        </form>

        <x-slot:footer>
            <button wire:click="closeDrawer" type="button" class="rounded-md px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700">Cancel</button>
            <button wire:click="save" type="button" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">Save</button>
        </x-slot:footer>
    </x-drawer>
</div>
