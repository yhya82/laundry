<div>
    <div class="mb-5">
        <h1 class="text-lg font-semibold text-slate-900 dark:text-white">Payments</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Every payment recorded against a laundry order, across all customers.</p>
    </div>

    <div class="mb-4 flex flex-wrap gap-3">
        <input type="text" wire:model.live.debounce.400ms="search" placeholder="Search customer or payment #" class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white">
        <select wire:model.live="statusFilter" class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white">
            <option value="">All statuses</option>
            <option value="pending">Pending</option>
            <option value="partial">Partial</option>
            <option value="paid">Paid</option>
            <option value="failed">Failed</option>
            <option value="refunded">Refunded</option>
            <option value="cancelled">Cancelled</option>
        </select>
        <select wire:model.live="methodFilter" class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white">
            <option value="">All methods</option>
            <option value="cash">Cash</option>
            <option value="card">Card</option>
            <option value="mobile_money">Mobile money</option>
            <option value="bank_transfer">Bank transfer</option>
            <option value="credit">Credit</option>
            <option value="store_credit">Store credit</option>
        </select>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-800">
                <tr>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Payment #</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Customer</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Order</th>
                    <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Amount</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Method</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-700 dark:bg-slate-900">
                @forelse ($payments as $payment)
                    <tr wire:key="pay-{{ $payment->id }}">
                        <td class="px-4 py-2.5 text-sm font-medium text-slate-800 dark:text-slate-100">{{ $payment->payment_number }}</td>
                        <td class="px-4 py-2.5 text-sm text-slate-600 dark:text-slate-300">
                            <a href="{{ route('customers.show', $payment->customer) }}" class="hover:text-sky-600 dark:hover:text-sky-400">{{ $payment->customer->name }}</a>
                        </td>
                        <td class="px-4 py-2.5 text-sm text-slate-600 dark:text-slate-300">
                            <a href="{{ route('orders.show', $payment->laundryOrder) }}" class="hover:text-sky-600 dark:hover:text-sky-400">{{ $payment->laundryOrder->order_number }}</a>
                        </td>
                        <td class="px-4 py-2.5 text-right text-sm tabular-nums text-slate-800 dark:text-slate-100">
                            {{ number_format($payment->amount, 2) }}
                            @if ($payment->refunds->isNotEmpty())
                                <span class="block text-xs text-rose-500">−{{ number_format($payment->refunds->sum('amount'), 2) }} refunded</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-sm capitalize text-slate-500 dark:text-slate-400">{{ str_replace('_', ' ', $payment->payment_method) }}</td>
                        <td class="px-4 py-2.5">
                            <span @class([
                                'inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize',
                                'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300' => $payment->payment_status === 'paid',
                                'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300' => $payment->payment_status === 'partial',
                                'bg-rose-100 text-rose-700 dark:bg-rose-900 dark:text-rose-300' => in_array($payment->payment_status, ['refunded', 'failed', 'cancelled']),
                                'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' => $payment->payment_status === 'pending',
                            ])>{{ $payment->payment_status }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-sm text-slate-500 dark:text-slate-400">{{ $payment->created_at->format('M j, Y g:ia') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-slate-400">No payments match this view.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $payments->links() }}</div>
</div>
