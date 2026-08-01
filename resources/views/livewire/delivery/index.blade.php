<div>
    <div class="mb-5">
        <h1 class="text-lg font-semibold text-slate-900 dark:text-white">Deliveries</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Every delivery scheduled against a laundry order.</p>
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <input type="text" wire:model.live.debounce.400ms="search" placeholder="Search customer, order #, or address" class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white">
        <select wire:model.live="statusFilter" class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white">
            <option value="">All statuses</option>
            <option value="pending">Pending</option>
            <option value="scheduled">Scheduled</option>
            <option value="assigned">Assigned</option>
            <option value="picked_up">Picked up</option>
            <option value="out_for_delivery">Out for delivery</option>
            <option value="delivered">Delivered</option>
            <option value="failed">Failed</option>
            <option value="cancelled">Cancelled</option>
        </select>
        <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
            <input type="checkbox" wire:model.live="myDeliveriesOnly" class="rounded border-slate-300 dark:border-slate-600">
            My deliveries only
        </label>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-800">
                <tr>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Customer</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Order</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Address</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Staff</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Scheduled</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-700 dark:bg-slate-900">
                @forelse ($deliveries as $delivery)
                    <tr wire:key="dlv-row-{{ $delivery->id }}">
                        <td class="px-4 py-2.5 text-sm font-medium text-slate-800 dark:text-slate-100">
                            <a href="{{ route('customers.show', $delivery->customer) }}" class="hover:text-sky-600 dark:hover:text-sky-400">{{ $delivery->customer->name }}</a>
                        </td>
                        <td class="px-4 py-2.5 text-sm text-slate-600 dark:text-slate-300">
                            <a href="{{ route('orders.show', $delivery->laundryOrder) }}" class="hover:text-sky-600 dark:hover:text-sky-400">{{ $delivery->laundryOrder->order_number }}</a>
                        </td>
                        <td class="px-4 py-2.5 text-sm text-slate-500 dark:text-slate-400">{{ \Illuminate\Support\Str::limit($delivery->address_snapshot, 40) }}</td>
                        <td class="px-4 py-2.5 text-sm text-slate-500 dark:text-slate-400">{{ $delivery->assignedStaff?->name ?? '—' }}</td>
                        <td class="px-4 py-2.5">
                            <span @class([
                                'inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize',
                                'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300' => $delivery->status === 'delivered',
                                'bg-rose-100 text-rose-700 dark:bg-rose-900 dark:text-rose-300' => in_array($delivery->status, ['failed', 'cancelled']),
                                'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' => ! in_array($delivery->status, ['delivered', 'failed', 'cancelled']),
                            ])>{{ str_replace('_', ' ', $delivery->status) }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-sm text-slate-500 dark:text-slate-400">{{ $delivery->scheduled_date?->format('M j, Y') ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-right text-sm">
                            <a href="{{ route('deliveries.show', $delivery) }}" class="font-medium text-sky-600 hover:text-sky-800 dark:text-sky-400">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-slate-400">No deliveries match this view.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $deliveries->links() }}</div>
</div>
