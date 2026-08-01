<div>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-900 dark:text-white">Laundry Orders</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Processing queue, sorted by stage, priority, then age.</p>
        </div>
        <div class="flex items-center gap-2">
            @can('discounts.manage')
                <a href="{{ route('discounts.index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400">Discount templates</a>
            @endcan
            @can('orders.create')
                <a href="{{ route('orders.terminal') }}" class="rounded-md bg-sky-600 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-700">
                    + New order
                </a>
            @endcan
        </div>
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <input
            type="search"
            wire:model.live.debounce.300ms="search"
            placeholder="Search order # or customer..."
            class="w-full max-w-xs rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
        >
        <select wire:model.live="statusFilter" class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white">
            <option value="">Active queue (all stages)</option>
            <option value="waiting_queue">Waiting queue</option>
            <option value="received">Received</option>
            <option value="sorting">Sorting</option>
            <option value="washing">Washing</option>
            <option value="drying">Drying</option>
            <option value="ironing">Ironing</option>
            <option value="quality_check">Quality check</option>
            <option value="packaging">Packaging</option>
            <option value="ready">Ready</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
        </select>
        <select wire:model.live="priorityFilter" class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white">
            <option value="">All priorities</option>
            <option value="express">Express</option>
            <option value="normal">Normal</option>
        </select>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-800">
                <tr>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Order #</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Customer</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Stage</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Priority</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Total</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Created</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-700 dark:bg-slate-900">
                @forelse ($orders as $order)
                    <tr wire:key="ord-{{ $order->id }}">
                        <td class="px-4 py-2.5 text-sm font-medium text-slate-800 dark:text-slate-100">
                            <a href="{{ route('orders.show', $order) }}" class="hover:text-sky-600 dark:hover:text-sky-400">{{ $order->order_number }}</a>
                        </td>
                        <td class="px-4 py-2.5 text-sm text-slate-600 dark:text-slate-300">{{ $order->customer->name }}</td>
                        <td class="px-4 py-2.5">
                            <span @class([
                                'inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize',
                                'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300' => $order->status === 'completed',
                                'bg-rose-100 text-rose-700 dark:bg-rose-900 dark:text-rose-300' => $order->status === 'cancelled',
                                'bg-sky-100 text-sky-700 dark:bg-sky-900 dark:text-sky-300' => ! in_array($order->status, ['completed', 'cancelled']),
                            ])>
                                {{ str_replace('_', ' ', $order->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5">
                            @if ($order->priority === 'express')
                                <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900 dark:text-amber-300">Express</span>
                            @else
                                <span class="text-sm text-slate-500 dark:text-slate-400">Normal</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-sm tabular-nums text-slate-600 dark:text-slate-300">{{ number_format($order->total_amount, 2) }}</td>
                        <td class="px-4 py-2.5 text-sm text-slate-500 dark:text-slate-400">{{ $order->created_at->diffForHumans() }}</td>
                        <td class="px-4 py-2.5 text-right text-sm">
                            <a href="{{ route('orders.show', $order) }}" class="font-medium text-sky-600 hover:text-sky-800 dark:text-sky-400">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-slate-400">No orders in this view.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $orders->links() }}</div>
</div>
