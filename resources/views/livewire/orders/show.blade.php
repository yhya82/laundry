<div>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-lg font-semibold text-slate-900 dark:text-white">{{ $order->order_number }}</h1>
                @if ($order->priority === 'express')
                    <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900 dark:text-amber-300">Express</span>
                @endif
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                <a href="{{ route('customers.show', $order->customer) }}" class="hover:text-sky-600 dark:hover:text-sky-400">{{ $order->customer->name }}</a>
                · {{ $order->delivery_type === 'pickup' ? 'Pickup' : 'Delivery' }} · placed {{ $order->created_at->diffForHumans() }}
            </p>
        </div>

        <div class="flex items-center gap-3">
            <div class="text-right text-sm">
                <p class="text-slate-500 dark:text-slate-400">Total</p>
                <p class="font-semibold tabular-nums text-slate-800 dark:text-slate-100">{{ number_format($order->total_amount, 2) }}</p>
            </div>
            @can('orders.cancel')
                @if (! in_array($order->status, ['completed', 'cancelled']))
                    <button wire:click="$set('showCancelDrawer', true)" type="button" class="rounded-md border border-rose-300 px-3 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50 dark:border-rose-800 dark:text-rose-400 dark:hover:bg-rose-950">
                        Cancel order
                    </button>
                @endif
            @endcan
        </div>
    </div>

    {{-- Stage timeline — §3.10: completed stages green, current stage active,
         no skipping. The UI only ever offers the single legal next stage;
         trg_laundry_orders_stage_sequence remains the real authority. --}}
    @if ($order->status !== 'cancelled')
        <div class="mb-6 overflow-x-auto rounded-lg border border-slate-200 p-4 dark:border-slate-700">
            <div class="flex min-w-max items-center">
                @foreach (\App\Services\LaundryOrderService::STAGES as $i => $stage)
                    @php
                        $currentIndex = array_search($order->status, \App\Services\LaundryOrderService::STAGES, true);
                        $isDone = $i < $currentIndex;
                        $isActive = $i === $currentIndex;
                    @endphp
                    <div class="flex items-center">
                        <div class="flex flex-col items-center">
                            <div @class([
                                'flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold',
                                'bg-emerald-500 text-white' => $isDone,
                                'bg-sky-600 text-white ring-4 ring-sky-100 dark:ring-sky-900' => $isActive,
                                'bg-slate-100 text-slate-400 dark:bg-slate-800' => ! $isDone && ! $isActive,
                            ])>
                                @if ($isDone) ✓ @else {{ $i + 1 }} @endif
                            </div>
                            <span @class([
                                'mt-1 w-20 text-center text-[11px] capitalize',
                                'font-medium text-slate-800 dark:text-slate-100' => $isActive,
                                'text-slate-500 dark:text-slate-400' => ! $isActive,
                            ])>{{ str_replace('_', ' ', $stage) }}</span>
                        </div>
                        @if (! $loop->last)
                            <div @class(['h-0.5 w-8 sm:w-12', 'bg-emerald-500' => $isDone, 'bg-slate-200 dark:bg-slate-700' => ! $isDone])></div>
                        @endif
                    </div>
                @endforeach
            </div>

            @can('orders.advance_stage')
                @if ($this->nextStage)
                    <div class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-700">
                        @if ($this->capacityWarning)
                            <p class="mb-2 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-700 dark:bg-amber-950 dark:text-amber-300">{{ $this->capacityWarning }}</p>
                        @endif
                        <button wire:click="advanceStage" type="button" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">
                            Advance to "{{ str_replace('_', ' ', $this->nextStage) }}"
                        </button>
                    </div>
                @endif
            @endcan
        </div>
    @else
        <div class="mb-6 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-800 dark:bg-rose-950 dark:text-rose-300">
            Cancelled {{ $order->cancelled_at?->diffForHumans() }} — {{ $order->cancelled_reason }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Packages</h2>
                <div class="space-y-3">
                    @foreach ($order->packages as $line)
                        <div class="rounded-md border border-slate-100 p-3 text-sm dark:border-slate-800">
                            <div class="flex justify-between">
                                <span class="font-medium text-slate-800 dark:text-slate-100">{{ $line->package->name }}</span>
                                <span class="tabular-nums text-slate-600 dark:text-slate-300">{{ number_format($line->line_total, 2) }}</span>
                            </div>
                            <ul class="mt-1 space-y-0.5 text-xs text-slate-500 dark:text-slate-400">
                                @foreach ($line->items as $item)
                                    <li>{{ $item->clothingType->name }} × {{ $item->quantity }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>

                @if ($order->instructions)
                    <p class="mt-3 border-t border-slate-100 pt-3 text-sm text-slate-500 dark:border-slate-800 dark:text-slate-400">
                        <strong class="text-slate-700 dark:text-slate-200">Instructions:</strong> {{ $order->instructions }}
                    </p>
                @endif
            </div>

            <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Payments</h2>
                    @if (! in_array($order->status, ['cancelled']) && bccomp($this->remainingBalance, '0', 2) > 0)
                        <div class="flex items-center gap-2">
                            @can('payments.store_credit')
                                @if ($order->customer->store_credit_balance > 0)
                                    <button wire:click="openApplyCreditDrawer" type="button" class="rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">
                                        Apply store credit
                                    </button>
                                @endif
                            @endcan
                            @can('payments.create')
                                <button wire:click="openPaymentDrawer" type="button" class="rounded-md bg-sky-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-sky-700">
                                    Record payment
                                </button>
                            @endcan
                        </div>
                    @endif
                </div>

                <p class="mb-2 text-xs text-slate-500 dark:text-slate-400">
                    Remaining balance: <span class="font-medium tabular-nums text-slate-700 dark:text-slate-200">{{ number_format($this->remainingBalance, 2) }}</span>
                </p>

                @forelse ($order->payments as $payment)
                    <div class="flex items-center justify-between border-b border-slate-100 py-2 text-sm last:border-0 dark:border-slate-800">
                        <div>
                            <span class="font-medium text-slate-800 dark:text-slate-100">{{ number_format($payment->amount, 2) }}</span>
                            <span class="text-slate-500 dark:text-slate-400"> via {{ str_replace('_', ' ', $payment->payment_method) }}</span>
                            @if ($payment->refunds->isNotEmpty())
                                <span class="block text-xs text-rose-500">Refunded {{ number_format($payment->refunds->sum('amount'), 2) }}</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            <span @class([
                                'inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize',
                                'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300' => $payment->payment_status === 'paid',
                                'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300' => $payment->payment_status === 'partial',
                                'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' => ! in_array($payment->payment_status, ['paid', 'partial']),
                            ])>{{ $payment->payment_status }}</span>
                            @can('payments.refund')
                                @if (in_array($payment->payment_status, ['paid', 'partial']) && bccomp($payment->amount, (string) $payment->refunds->sum('amount'), 2) > 0)
                                    <button wire:click="openRefundDrawer({{ $payment->id }})" type="button" class="text-xs font-medium text-rose-600 hover:underline dark:text-rose-400">Refund</button>
                                @endif
                            @endcan
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-400">No payments recorded yet.</p>
                @endforelse

                @if ($order->receipt)
                    <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-3 dark:border-slate-800">
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            Receipt <strong class="text-slate-700 dark:text-slate-200">{{ $order->receipt->receipt_number }}</strong>
                            @if ($order->receipt->status === 'cancelled')
                                <span class="text-rose-500">(cancelled)</span>
                            @endif
                        </p>
                        <div class="flex items-center gap-3">
                            @can('receipts.print')
                                <a href="{{ route('receipts.pdf', $order->receipt) }}" target="_blank" class="text-xs font-medium text-sky-600 hover:underline dark:text-sky-400">View / print</a>
                            @endcan
                            @can('receipts.cancel')
                                @if ($order->receipt->status !== 'cancelled')
                                    <button wire:click="$set('showCancelReceiptDrawer', true)" type="button" class="text-xs font-medium text-rose-600 hover:underline dark:text-rose-400">Cancel receipt</button>
                                @endif
                            @endcan
                        </div>
                    </div>
                @endif
            </div>

            @if ($order->discounts->isNotEmpty())
                <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                    <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Discounts</h2>
                    @foreach ($order->discounts as $discount)
                        <div class="flex items-center justify-between border-b border-slate-100 py-2 text-sm last:border-0 dark:border-slate-800">
                            <div>
                                <span class="text-slate-700 dark:text-slate-200">{{ $discount->reason }}</span>
                                <span class="text-slate-400"> ({{ $discount->discount_type === 'percentage' ? $discount->value.'%' : number_format($discount->value, 2) }})</span>
                            </div>
                            <span class="tabular-nums text-slate-600 dark:text-slate-300">−{{ number_format($discount->amount, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Damage reports</h2>
                    @can('damage.create')
                        @if ($order->status !== 'cancelled')
                            <button wire:click="openDamageDrawer" type="button" class="rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">
                                Report damage
                            </button>
                        @endif
                    @endcan
                </div>

                @forelse ($order->damageReports as $report)
                    <a href="{{ route('damage.show', $report) }}" wire:key="dmg-{{ $report->id }}" class="flex items-center justify-between border-b border-slate-100 py-2 text-sm last:border-0 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/50">
                        <span class="text-slate-700 dark:text-slate-200">{{ \Illuminate\Support\Str::limit($report->description, 50) }}</span>
                        <span @class([
                            'inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize',
                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300' => $report->status === 'resolved',
                            'bg-rose-100 text-rose-700 dark:bg-rose-900 dark:text-rose-300' => $report->status === 'rejected',
                            'bg-sky-100 text-sky-700 dark:bg-sky-900 dark:text-sky-300' => in_array($report->status, ['approved']),
                            'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' => in_array($report->status, ['reported', 'under_review']),
                        ])>{{ str_replace('_', ' ', $report->status) }}</span>
                    </a>
                @empty
                    <p class="text-sm text-slate-400">No damage reported on this order.</p>
                @endforelse
            </div>

            @if ($order->delivery_type === 'delivery')
                <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Delivery</h2>
                        @can('deliveries.manage')
                            @if (! $order->delivery)
                                <button wire:click="openDeliveryDrawer" type="button" class="rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">
                                    Schedule delivery
                                </button>
                            @endif
                        @endcan
                    </div>

                    @if ($order->delivery)
                        <a href="{{ route('deliveries.show', $order->delivery) }}" class="flex items-center justify-between rounded-md border border-slate-100 p-2.5 text-sm hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/50">
                            <div>
                                <span class="text-slate-700 dark:text-slate-200">{{ $order->delivery->address_snapshot ?: 'No address on file' }}</span>
                                @if ($order->delivery->scheduled_date)
                                    <span class="block text-xs text-slate-400">Scheduled {{ $order->delivery->scheduled_date->format('M j, Y') }}</span>
                                @endif
                            </div>
                            <span @class([
                                'inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize',
                                'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300' => $order->delivery->status === 'delivered',
                                'bg-rose-100 text-rose-700 dark:bg-rose-900 dark:text-rose-300' => in_array($order->delivery->status, ['failed', 'cancelled']),
                                'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' => ! in_array($order->delivery->status, ['delivered', 'failed', 'cancelled']),
                            ])>{{ str_replace('_', ' ', $order->delivery->status) }}</span>
                        </a>
                    @else
                        <p class="text-sm text-slate-400">No delivery scheduled yet.</p>
                    @endif
                </div>
            @endif
        </div>

        <div class="space-y-6">
            @can('orders.assign_staff')
                <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                    <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Assignment</h2>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400">Employee</label>
                    <select wire:model="assignEmployeeId" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                        <option value="">Unassigned</option>
                        @foreach ($this->employees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                        @endforeach
                    </select>
                    <label class="mt-3 block text-xs font-medium text-slate-500 dark:text-slate-400">Machine</label>
                    <select wire:model="assignMachineId" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                        <option value="">Unassigned</option>
                        @foreach ($this->machines as $machine)
                            <option value="{{ $machine->id }}">{{ $machine->name }} ({{ $machine->status }})</option>
                        @endforeach
                    </select>
                    <button wire:click="saveAssignment" type="button" class="mt-3 w-full rounded-md bg-sky-600 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-700">Save assignment</button>
                </div>
            @endcan

            <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Stage history</h2>
                <div class="space-y-2">
                    @foreach ($order->stageHistory->sortByDesc('started_at') as $history)
                        <div class="text-xs">
                            <span class="font-medium capitalize text-slate-700 dark:text-slate-200">{{ str_replace('_', ' ', $history->stage) }}</span>
                            <span class="text-slate-400"> — {{ $history->started_at->format('M j, g:ia') }}{{ $history->completed_at ? ' → '.$history->completed_at->format('g:ia') : ' (current)' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <x-drawer :show="$showCancelDrawer" title="Cancel order">
        <form wire:submit="cancelOrder" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Reason</label>
                <textarea wire:model="cancelReason" rows="3" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white"></textarea>
                @error('cancelReason') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
        </form>

        <x-slot:footer>
            <button wire:click="$set('showCancelDrawer', false)" type="button" class="rounded-md px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700">Back</button>
            <button wire:click="cancelOrder" type="button" class="rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Cancel order</button>
        </x-slot:footer>
    </x-drawer>

    <x-drawer :show="$showPaymentDrawer" title="Record payment">
        <form wire:submit="recordPayment" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Amount</label>
                <input type="text" inputmode="decimal" wire:model="paymentAmount" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                @error('paymentAmount') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Method</label>
                <select wire:model="paymentMethod" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                    <option value="cash">Cash</option>
                    <option value="card">Card</option>
                    <option value="mobile_money">Mobile money</option>
                    <option value="bank_transfer">Bank transfer</option>
                    <option value="credit">Credit</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Reference (optional)</label>
                <input type="text" wire:model="paymentReference" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                @error('paymentReference') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
        </form>

        <x-slot:footer>
            <button wire:click="$set('showPaymentDrawer', false)" type="button" class="rounded-md px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700">Cancel</button>
            <button wire:click="recordPayment" type="button" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">Record payment</button>
        </x-slot:footer>
    </x-drawer>

    <x-drawer :show="$showRefundDrawer" title="Issue refund">
        <form wire:submit="refundPayment" class="space-y-4">
            <p class="text-sm text-slate-500 dark:text-slate-400">Up to {{ number_format($this->refundMax, 2) }} refundable on this payment.</p>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Amount</label>
                <input type="text" inputmode="decimal" wire:model="refundAmount" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                @error('refundAmount') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Reason</label>
                <textarea wire:model="refundReason" rows="2" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white"></textarea>
                @error('refundReason') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input type="checkbox" wire:model="refundAsStoreCredit" class="rounded border-slate-300 dark:border-slate-600">
                Issue as store credit instead of cash
            </label>
        </form>

        <x-slot:footer>
            <button wire:click="$set('showRefundDrawer', false)" type="button" class="rounded-md px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700">Cancel</button>
            <button wire:click="refundPayment" type="button" class="rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Issue refund</button>
        </x-slot:footer>
    </x-drawer>

    <x-drawer :show="$showApplyCreditDrawer" title="Apply store credit">
        <form wire:submit="applyStoreCredit" class="space-y-4">
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Customer has {{ number_format($order->customer->store_credit_balance, 2) }} available.
            </p>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Amount to apply</label>
                <input type="text" inputmode="decimal" wire:model="applyCreditAmount" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                @error('applyCreditAmount') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
        </form>

        <x-slot:footer>
            <button wire:click="$set('showApplyCreditDrawer', false)" type="button" class="rounded-md px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700">Cancel</button>
            <button wire:click="applyStoreCredit" type="button" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">Apply</button>
        </x-slot:footer>
    </x-drawer>

    <x-drawer :show="$showCancelReceiptDrawer" title="Cancel receipt">
        <form wire:submit="cancelReceipt" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Reason</label>
                <textarea wire:model="cancelReceiptReason" rows="3" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white"></textarea>
                @error('cancelReceiptReason') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
        </form>

        <x-slot:footer>
            <button wire:click="$set('showCancelReceiptDrawer', false)" type="button" class="rounded-md px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700">Back</button>
            <button wire:click="cancelReceipt" type="button" class="rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Cancel receipt</button>
        </x-slot:footer>
    </x-drawer>

    <x-drawer :show="$showDamageDrawer" title="Report damage">
        <form wire:submit="reportDamage" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Description</label>
                <textarea wire:model="damageDescription" rows="2" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white"></textarea>
                @error('damageDescription') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Affected item</label>
                <div class="mt-1 flex gap-2">
                    <select wire:model="pendingItemId" class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                        <option value="">Select an item…</option>
                        @foreach ($this->orderItemOptions as $option)
                            <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                    <button wire:click="addDamageItem" type="button" class="shrink-0 rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">Add</button>
                </div>
                @error('damageItems') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            @if (! empty($damageItems))
                <div class="space-y-3">
                    @foreach ($damageItems as $index => $line)
                        <div wire:key="dmg-line-{{ $index }}" class="rounded-md border border-slate-200 p-3 dark:border-slate-700">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ $line['item_label'] }}</span>
                                <button wire:click="removeDamageItem({{ $index }})" type="button" class="text-xs text-rose-600 hover:underline dark:text-rose-400">Remove</button>
                            </div>
                            <div class="mt-2 grid grid-cols-2 gap-2">
                                <select wire:model="damageItems.{{ $index }}.damage_type_id" class="rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                                    <option value="">Damage type…</option>
                                    @foreach ($this->damageTypes as $type)
                                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                                    @endforeach
                                </select>
                                <select wire:model="damageItems.{{ $index }}.severity" class="rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>
                            @error("damageItems.{$index}.damage_type_id") <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                            <input type="text" wire:model="damageItems.{{ $index }}.description" placeholder="Note (optional)" class="mt-2 block w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                        </div>
                    @endforeach
                </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Evidence photos (optional)</label>
                <input type="file" wire:model="damageEvidence" multiple accept="image/*" class="mt-1 block w-full text-sm text-slate-600 dark:text-slate-300">
                @error('damageEvidence.*') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
        </form>

        <x-slot:footer>
            <button wire:click="$set('showDamageDrawer', false)" type="button" class="rounded-md px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700">Cancel</button>
            <button wire:click="reportDamage" type="button" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">File report</button>
        </x-slot:footer>
    </x-drawer>

    <x-drawer :show="$showDeliveryDrawer" title="Schedule delivery">
        <form wire:submit="scheduleDelivery" class="space-y-4">
            @if ($order->customer->addresses->isNotEmpty())
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Saved address</label>
                    <select wire:model="deliveryAddressId" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                        <option value="">Enter manually…</option>
                        @foreach ($order->customer->addresses as $address)
                            <option value="{{ $address->id }}">{{ collect([$address->label, $address->street, $address->city])->filter()->implode(' — ') }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if (! $deliveryAddressId)
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Address</label>
                    <input type="text" wire:model="deliveryAddressSnapshot" placeholder="Street, area, city" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                    @error('deliveryAddressSnapshot') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Delivery fee</label>
                <input type="text" inputmode="decimal" wire:model="deliveryFee" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                @error('deliveryFee') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                <p class="mt-1 text-xs text-slate-400">Added to the order total immediately.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Scheduled date (optional)</label>
                <input type="date" wire:model="deliveryScheduledDate" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Instructions (optional)</label>
                <input type="text" wire:model="deliveryInstructions" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
            </div>
        </form>

        <x-slot:footer>
            <button wire:click="$set('showDeliveryDrawer', false)" type="button" class="rounded-md px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700">Cancel</button>
            <button wire:click="scheduleDelivery" type="button" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">Schedule</button>
        </x-slot:footer>
    </x-drawer>
</div>
