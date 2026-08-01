<div>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="flex h-14 w-14 items-center justify-center rounded-full bg-sky-100 text-lg font-semibold text-sky-700 dark:bg-sky-900 dark:text-sky-300">
                {{ mb_substr($customer->name, 0, 1) }}
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-lg font-semibold text-slate-900 dark:text-white">{{ $customer->name }}</h1>
                    <span @class([
                        'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300' => $customer->status === 'active',
                        'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' => $customer->status !== 'active',
                    ])>
                        {{ ucfirst($customer->status) }}
                    </span>
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ $customer->phone }}{{ $customer->email ? ' · '.$customer->email : '' }} ·
                    {{ $customer->customer_type === 'walk_in' ? 'Walk-in' : 'Subscription' }} customer
                </p>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <div class="text-right text-sm">
                <p class="text-slate-500 dark:text-slate-400">Outstanding</p>
                <p class="font-semibold tabular-nums text-slate-800 dark:text-slate-100">{{ number_format($customer->outstanding_balance, 2) }}</p>
            </div>
            <div class="text-right text-sm">
                <p class="text-slate-500 dark:text-slate-400">Store credit</p>
                <p class="font-semibold tabular-nums text-slate-800 dark:text-slate-100">{{ number_format($customer->store_credit_balance, 2) }}</p>
            </div>
            @can('customers.update')
                <button wire:click="editCustomer" type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">
                    Edit
                </button>
            @endcan
        </div>
    </div>

    <div class="mb-5 border-b border-slate-200 dark:border-slate-700">
        <nav class="-mb-px flex flex-wrap gap-4">
            @foreach ($tabs as $t)
                <button
                    wire:click="setTab('{{ $t['key'] }}')"
                    type="button"
                    @class([
                        'border-b-2 px-1 py-2.5 text-sm font-medium',
                        'border-sky-600 text-sky-600 dark:text-sky-400' => $tab === $t['key'],
                        'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' => $tab !== $t['key'],
                    ])
                >
                    {{ $t['label'] }}
                </button>
            @endforeach
        </nav>
    </div>

    @if ($tab === 'overview')
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <livewire:customers.addresses :customer="$customer" :key="'addr-'.$customer->id" />
            <livewire:customers.notes :customer="$customer" :key="'notes-'.$customer->id" />
        </div>
    @elseif ($tab === 'payments')
        <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-800">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Payment #</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Order</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Amount</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Method</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-700 dark:bg-slate-900">
                    @forelse ($this->payments as $payment)
                        <tr wire:key="cust-pay-{{ $payment->id }}">
                            <td class="px-4 py-2.5 text-sm font-medium text-slate-800 dark:text-slate-100">{{ $payment->payment_number }}</td>
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
                        <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-slate-400">No payments recorded for this customer yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @elseif ($tab === 'receipts')
        <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-800">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Receipt #</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Order</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Total</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Generated</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-700 dark:bg-slate-900">
                    @forelse ($this->receipts as $receipt)
                        <tr wire:key="cust-rcp-{{ $receipt->id }}">
                            <td class="px-4 py-2.5 text-sm font-medium text-slate-800 dark:text-slate-100">{{ $receipt->receipt_number }}</td>
                            <td class="px-4 py-2.5 text-sm text-slate-600 dark:text-slate-300">
                                <a href="{{ route('orders.show', $receipt->laundryOrder) }}" class="hover:text-sky-600 dark:hover:text-sky-400">{{ $receipt->laundryOrder->order_number }}</a>
                            </td>
                            <td class="px-4 py-2.5 text-right text-sm tabular-nums text-slate-800 dark:text-slate-100">{{ number_format($receipt->total_snapshot, 2) }}</td>
                            <td class="px-4 py-2.5">
                                <span @class([
                                    'inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize',
                                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300' => $receipt->status !== 'cancelled',
                                    'bg-rose-100 text-rose-700 dark:bg-rose-900 dark:text-rose-300' => $receipt->status === 'cancelled',
                                ])>{{ $receipt->status }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-sm text-slate-500 dark:text-slate-400">{{ $receipt->generated_at->format('M j, Y g:ia') }}</td>
                            <td class="px-4 py-2.5 text-right text-sm">
                                @can('receipts.print')
                                    <a href="{{ route('receipts.pdf', $receipt) }}" target="_blank" class="font-medium text-sky-600 hover:underline dark:text-sky-400">View / print</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-slate-400">No receipts generated for this customer yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @elseif ($tab === 'damages')
        <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-800">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Order</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Description</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Filed</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-700 dark:bg-slate-900">
                    @forelse ($this->damageReports as $report)
                        <tr wire:key="cust-dmg-{{ $report->id }}">
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
                        <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-slate-400">No damage reported for this customer yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @elseif ($tab === 'history')
        <div class="space-y-3">
            @forelse ($this->timeline as $event)
                <div wire:key="tl-{{ $event->id }}" class="flex gap-3 rounded-md border border-slate-200 p-3 text-sm dark:border-slate-700">
                    <div class="mt-0.5 h-2 w-2 shrink-0 rounded-full bg-sky-500"></div>
                    <div>
                        <p class="font-medium text-slate-800 dark:text-slate-100">{{ $event->title }}</p>
                        @if ($event->description)
                            <p class="text-slate-500 dark:text-slate-400">{{ $event->description }}</p>
                        @endif
                        <p class="mt-0.5 text-xs text-slate-400">{{ $event->occurred_at->format('M j, Y g:ia') }}</p>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-400">No activity recorded yet — this fills in as orders, payments, and other events happen.</p>
            @endforelse
        </div>
    @else
        @php $meta = collect($tabs)->firstWhere('key', $tab); @endphp
        <div class="flex flex-col items-center py-16 text-center">
            <p class="text-sm font-medium text-slate-600 dark:text-slate-300">{{ $meta['label'] }} isn't built yet</p>
            <p class="mt-1 max-w-sm text-sm text-slate-400">This tab fills in once its owning module lands later in the build — see IMPLEMENTATION_PLAN.md.</p>
        </div>
    @endif

    <x-drawer :show="$showEditDrawer" title="Edit customer">
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
