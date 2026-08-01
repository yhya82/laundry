<div>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-900 dark:text-white">Reports</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Revenue, expenses, and orders over a date range — live data, not the daily_statistics snapshot.</p>
        </div>
        @can('reports.export')
            <div class="flex items-center gap-2">
                @foreach (['pdf' => 'PDF', 'csv' => 'CSV', 'excel' => 'Excel'] as $format => $label)
                    <a
                        href="{{ route('reports.export', ['format' => $format, 'report_type' => $reportType, 'from' => $from, 'to' => $to]) }}"
                        target="_blank"
                        class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800"
                    >{{ $label }}</a>
                @endforeach
            </div>
        @endcan
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <select wire:model.live="reportType" class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white">
            <option value="revenue">Revenue</option>
            <option value="expenses">Expenses</option>
            <option value="orders">Orders</option>
        </select>
        <input type="date" wire:model.live="from" class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white">
        <span class="text-sm text-slate-400">to</span>
        <input type="date" wire:model.live="to" class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white">
    </div>

    @if ($reportType === 'revenue')
        <div class="mb-4 grid grid-cols-3 gap-4">
            <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                <p class="text-lg font-semibold tabular-nums text-slate-900 dark:text-white">{{ number_format($data['total_revenue'], 2) }}</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Total revenue</p>
            </div>
            <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                <p class="text-lg font-semibold tabular-nums text-slate-900 dark:text-white">{{ number_format($data['total_refunds'], 2) }}</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Total refunds</p>
            </div>
            <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                <p class="text-lg font-semibold tabular-nums text-slate-900 dark:text-white">{{ number_format($data['net_revenue'], 2) }}</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Net revenue</p>
            </div>
        </div>

        <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-800">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Payment #</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Customer</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Order</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Amount</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-700 dark:bg-slate-900">
                    @forelse ($data['rows'] as $payment)
                        <tr wire:key="rev-{{ $payment->id }}">
                            <td class="px-4 py-2.5 text-sm font-medium text-slate-800 dark:text-slate-100">{{ $payment->payment_number }}</td>
                            <td class="px-4 py-2.5 text-sm text-slate-600 dark:text-slate-300">{{ $payment->customer->name }}</td>
                            <td class="px-4 py-2.5 text-sm text-slate-600 dark:text-slate-300">
                                <a href="{{ route('orders.show', $payment->laundryOrder) }}" class="hover:text-sky-600 dark:hover:text-sky-400">{{ $payment->laundryOrder->order_number }}</a>
                            </td>
                            <td class="px-4 py-2.5 text-right text-sm tabular-nums text-slate-800 dark:text-slate-100">{{ number_format($payment->amount, 2) }}</td>
                            <td class="px-4 py-2.5 text-sm text-slate-500 dark:text-slate-400">{{ $payment->created_at->format('M j, Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-slate-400">No payments in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @elseif ($reportType === 'expenses')
        <div class="mb-4 flex flex-wrap gap-4">
            @foreach ($data['by_category'] as $category => $amount)
                <div class="rounded-lg border border-slate-200 px-4 py-2 dark:border-slate-700">
                    <p class="text-sm font-semibold tabular-nums text-slate-900 dark:text-white">{{ number_format($amount, 2) }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $category }}</p>
                </div>
            @endforeach
            <div class="rounded-lg border border-sky-200 bg-sky-50 px-4 py-2 dark:border-sky-800 dark:bg-sky-950">
                <p class="text-sm font-semibold tabular-nums text-sky-700 dark:text-sky-300">{{ number_format($data['total'], 2) }}</p>
                <p class="text-xs text-sky-600 dark:text-sky-400">Total</p>
            </div>
        </div>

        <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-800">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Date</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Title</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Category</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Amount</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-700 dark:bg-slate-900">
                    @forelse ($data['rows'] as $expense)
                        <tr wire:key="exp-{{ $expense->id }}">
                            <td class="px-4 py-2.5 text-sm text-slate-500 dark:text-slate-400">{{ $expense->expense_date->format('M j, Y') }}</td>
                            <td class="px-4 py-2.5 text-sm font-medium text-slate-800 dark:text-slate-100">{{ $expense->title }}</td>
                            <td class="px-4 py-2.5 text-sm text-slate-500 dark:text-slate-400">{{ $expense->category->name }}</td>
                            <td class="px-4 py-2.5 text-right text-sm tabular-nums text-slate-800 dark:text-slate-100">{{ number_format($expense->amount, 2) }}</td>
                            <td class="px-4 py-2.5 text-sm capitalize text-slate-500 dark:text-slate-400">{{ $expense->status }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-slate-400">No expenses in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        <div class="mb-4 flex flex-wrap gap-4">
            @foreach ($data['by_status'] as $status => $count)
                <div class="rounded-lg border border-slate-200 px-4 py-2 dark:border-slate-700">
                    <p class="text-sm font-semibold tabular-nums text-slate-900 dark:text-white">{{ $count }}</p>
                    <p class="text-xs capitalize text-slate-500 dark:text-slate-400">{{ str_replace('_', ' ', $status) }}</p>
                </div>
            @endforeach
        </div>

        <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-800">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Order #</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Customer</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Total</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-700 dark:bg-slate-900">
                    @forelse ($data['rows'] as $order)
                        <tr wire:key="ord-{{ $order->id }}">
                            <td class="px-4 py-2.5 text-sm font-medium text-slate-800 dark:text-slate-100">
                                <a href="{{ route('orders.show', $order) }}" class="hover:text-sky-600 dark:hover:text-sky-400">{{ $order->order_number }}</a>
                            </td>
                            <td class="px-4 py-2.5 text-sm text-slate-600 dark:text-slate-300">{{ $order->customer->name }}</td>
                            <td class="px-4 py-2.5 text-sm capitalize text-slate-500 dark:text-slate-400">{{ str_replace('_', ' ', $order->status) }}</td>
                            <td class="px-4 py-2.5 text-right text-sm tabular-nums text-slate-800 dark:text-slate-100">{{ number_format($order->total_amount, 2) }}</td>
                            <td class="px-4 py-2.5 text-sm text-slate-500 dark:text-slate-400">{{ $order->created_at->format('M j, Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-slate-400">No orders in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
