<div>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-900 dark:text-white">Collections</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Scheduled pickups generated from active subscriptions.</p>
        </div>
        @can('collections.manage')
            <button wire:click="generateNow" wire:confirm="Generate all collections due today or earlier?" type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">
                Generate due collections now
            </button>
        @endcan
    </div>

    <div class="mb-4">
        <select wire:model.live="statusFilter" class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white">
            <option value="">All statuses</option>
            <option value="scheduled">Scheduled</option>
            <option value="upcoming">Upcoming</option>
            <option value="collected">Collected</option>
            <option value="laundry_created">Laundry created</option>
            <option value="processing">Processing</option>
            <option value="completed">Completed</option>
            <option value="missed">Missed</option>
            <option value="rescheduled">Rescheduled</option>
            <option value="cancelled">Cancelled</option>
        </select>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-800">
                <tr>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Customer</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Scheduled</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Packages</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-700 dark:bg-slate-900">
                @forelse ($collections as $collection)
                    <tr wire:key="col-{{ $collection->id }}">
                        <td class="px-4 py-2.5 text-sm font-medium text-slate-800 dark:text-slate-100">
                            <a href="{{ route('customers.show', $collection->customer) }}" class="hover:text-sky-600 dark:hover:text-sky-400">{{ $collection->customer->name }}</a>
                        </td>
                        <td class="px-4 py-2.5 text-sm tabular-nums text-slate-600 dark:text-slate-300">{{ $collection->scheduled_date->format('M j, Y') }}</td>
                        <td class="px-4 py-2.5 text-sm text-slate-500 dark:text-slate-400">{{ $collection->package_quantity ?? '—' }}</td>
                        <td class="px-4 py-2.5">
                            <span @class([
                                'inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize',
                                'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300' => in_array($collection->status, ['completed', 'laundry_created']),
                                'bg-rose-100 text-rose-700 dark:bg-rose-900 dark:text-rose-300' => in_array($collection->status, ['missed', 'cancelled']),
                                'bg-sky-100 text-sky-700 dark:bg-sky-900 dark:text-sky-300' => ! in_array($collection->status, ['completed', 'laundry_created', 'missed', 'cancelled']),
                            ])>
                                {{ str_replace('_', ' ', $collection->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5 text-right text-sm">
                            @can('collections.manage')
                                @if ($collection->laundry_order_id)
                                    <a href="{{ route('orders.show', $collection->laundry_order_id) }}" class="font-medium text-sky-600 hover:text-sky-800 dark:text-sky-400">View order</a>
                                @else
                                    <button
                                        wire:click="convert({{ $collection->id }})"
                                        wire:confirm="Convert this collection into a laundry order?"
                                        type="button"
                                        class="font-medium text-emerald-600 hover:text-emerald-800 dark:text-emerald-400"
                                    >
                                        Convert to order
                                    </button>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-slate-400">No collections in this view.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $collections->links() }}</div>
</div>
