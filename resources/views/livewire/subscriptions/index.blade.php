<div>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-900 dark:text-white">Subscriptions</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Recurring pickup schedules and the packages each one includes.</p>
        </div>
        @can('subscriptions.manage')
            <button wire:click="create" type="button" class="rounded-md bg-sky-600 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-700">
                + New subscription
            </button>
        @endcan
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <input
            type="search"
            wire:model.live.debounce.300ms="search"
            placeholder="Search customer..."
            class="w-full max-w-xs rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white"
        >
        <select wire:model.live="statusFilter" class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white">
            <option value="">All statuses</option>
            <option value="active">Active</option>
            <option value="paused">Paused</option>
            <option value="suspended">Suspended</option>
            <option value="expired">Expired</option>
            <option value="cancelled">Cancelled</option>
        </select>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-800">
                <tr>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Customer</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Packages</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Frequency</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Next collection</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-700 dark:bg-slate-900">
                @forelse ($subscriptions as $subscription)
                    <tr wire:key="sub-{{ $subscription->id }}">
                        <td class="px-4 py-2.5 text-sm font-medium text-slate-800 dark:text-slate-100">
                            <a href="{{ route('customers.show', $subscription->customer) }}" class="hover:text-sky-600 dark:hover:text-sky-400">{{ $subscription->customer->name }}</a>
                        </td>
                        <td class="px-4 py-2.5 text-sm text-slate-500 dark:text-slate-400">
                            {{ $subscription->subscriptionPackages->map(fn ($sp) => $sp->package->name.' ×'.$sp->quantity)->join(', ') }}
                        </td>
                        <td class="px-4 py-2.5 text-sm text-slate-500 dark:text-slate-400">
                            {{ $subscription->frequency_type === 'custom' ? 'Every '.($subscription->custom_frequency_config['interval_days'] ?? '?').' days' : str_replace('_', ' ', $subscription->frequency_type) }}
                        </td>
                        <td class="px-4 py-2.5 text-sm tabular-nums text-slate-600 dark:text-slate-300">{{ $subscription->next_collection_date?->format('M j, Y') ?? '—' }}</td>
                        <td class="px-4 py-2.5">
                            <span @class([
                                'inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize',
                                'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300' => $subscription->status === 'active',
                                'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300' => $subscription->status === 'paused',
                                'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' => ! in_array($subscription->status, ['active', 'paused']),
                            ])>
                                {{ $subscription->status }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5 text-right text-sm">
                            @can('subscriptions.manage')
                                <button wire:click="edit({{ $subscription->id }})" class="font-medium text-sky-600 hover:text-sky-800 dark:text-sky-400">Edit</button>
                                @if (in_array($subscription->status, ['active', 'paused']))
                                    <button wire:click="togglePause({{ $subscription->id }})" class="ml-3 font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400">
                                        {{ $subscription->status === 'active' ? 'Pause' : 'Resume' }}
                                    </button>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-slate-400">No subscriptions yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $subscriptions->links() }}</div>

    <x-drawer :show="$showDrawer" :title="$editing ? 'Edit subscription' : 'New subscription'">
        <form wire:submit="save" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Customer</label>
                <select wire:model="customer_id" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                    <option value="">Select a customer</option>
                    @foreach ($this->customers as $customer)
                        <option value="{{ $customer->id }}">{{ $customer->name }} ({{ $customer->phone }})</option>
                    @endforeach
                </select>
                @error('customer_id') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Start date</label>
                    <input type="date" wire:model="start_date" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                    @error('start_date') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Status</label>
                    <select wire:model="status" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                        <option value="active">Active</option>
                        <option value="paused">Paused</option>
                        <option value="suspended">Suspended</option>
                        <option value="expired">Expired</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Frequency</label>
                <select wire:model.live="frequency_type" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                    <option value="monthly_1">Once a month</option>
                    <option value="monthly_2">Twice a month</option>
                    <option value="monthly_3">Three times a month</option>
                    <option value="monthly_4">Weekly (four times a month)</option>
                    <option value="custom">Custom</option>
                </select>
                @if ($frequency_type === 'custom')
                    <div class="mt-2">
                        <label class="block text-xs font-medium text-slate-500 dark:text-slate-400">Days between collections</label>
                        <input type="number" min="1" wire:model="custom_interval_days" class="mt-1 block w-full max-w-[8rem] rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                        @error('custom_interval_days') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                @endif
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Packages</label>
                <div class="mt-1 space-y-2">
                    @foreach ($packageLines as $index => $line)
                        <div class="flex items-center gap-2">
                            <select wire:model="packageLines.{{ $index }}.package_id" class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                                <option value="">Select package</option>
                                @foreach ($this->packages as $package)
                                    <option value="{{ $package->id }}">{{ $package->name }}</option>
                                @endforeach
                            </select>
                            <input type="number" min="1" wire:model="packageLines.{{ $index }}.quantity" class="w-20 rounded-md border border-slate-300 px-2 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                            <button wire:click="removePackageLine({{ $index }})" type="button" class="text-rose-500 hover:text-rose-700">×</button>
                        </div>
                    @endforeach
                </div>
                @error('packageLines') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                <button wire:click="addPackageLine" type="button" class="mt-2 text-sm font-medium text-sky-600 hover:text-sky-800 dark:text-sky-400">+ Add package</button>
            </div>
        </form>

        <x-slot:footer>
            <button wire:click="closeDrawer" type="button" class="rounded-md px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700">Cancel</button>
            <button wire:click="save" type="button" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">Save</button>
        </x-slot:footer>
    </x-drawer>
</div>
