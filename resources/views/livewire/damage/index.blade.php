<div>
    <div class="mb-5">
        <h1 class="text-lg font-semibold text-slate-900 dark:text-white">Damage Reports</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Every damage report filed against a laundry order, across all customers.</p>
    </div>

    <div class="mb-4 flex flex-wrap gap-3">
        <input type="text" wire:model.live.debounce.400ms="search" placeholder="Search customer, order #, or description" class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white">
        <select wire:model.live="statusFilter" class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white">
            <option value="">All statuses</option>
            <option value="reported">Reported</option>
            <option value="under_review">Under review</option>
            <option value="approved">Approved</option>
            <option value="resolved">Resolved</option>
            <option value="rejected">Rejected</option>
        </select>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-800">
                <tr>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Customer</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Order</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Description</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Filed</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-700 dark:bg-slate-900">
                @forelse ($reports as $report)
                    <tr wire:key="dmg-row-{{ $report->id }}">
                        <td class="px-4 py-2.5 text-sm font-medium text-slate-800 dark:text-slate-100">
                            <a href="{{ route('customers.show', $report->customer) }}" class="hover:text-sky-600 dark:hover:text-sky-400">{{ $report->customer->name }}</a>
                        </td>
                        <td class="px-4 py-2.5 text-sm text-slate-600 dark:text-slate-300">
                            <a href="{{ route('orders.show', $report->laundryOrder) }}" class="hover:text-sky-600 dark:hover:text-sky-400">{{ $report->laundryOrder->order_number }}</a>
                        </td>
                        <td class="px-4 py-2.5 text-sm text-slate-500 dark:text-slate-400">{{ \Illuminate\Support\Str::limit($report->description, 60) }}</td>
                        <td class="px-4 py-2.5">
                            <span @class([
                                'inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize',
                                'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300' => $report->status === 'resolved',
                                'bg-rose-100 text-rose-700 dark:bg-rose-900 dark:text-rose-300' => $report->status === 'rejected',
                                'bg-sky-100 text-sky-700 dark:bg-sky-900 dark:text-sky-300' => $report->status === 'approved',
                                'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' => in_array($report->status, ['reported', 'under_review']),
                            ])>{{ str_replace('_', ' ', $report->status) }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-sm text-slate-500 dark:text-slate-400">{{ $report->created_at->format('M j, Y g:ia') }}</td>
                        <td class="px-4 py-2.5 text-right text-sm">
                            <a href="{{ route('damage.show', $report) }}" class="font-medium text-sky-600 hover:text-sky-800 dark:text-sky-400">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-slate-400">No damage reports match this view.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $reports->links() }}</div>
</div>
