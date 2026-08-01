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
                <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Payments</h2>
                @forelse ($order->payments as $payment)
                    <div class="flex items-center justify-between border-b border-slate-100 py-2 text-sm last:border-0 dark:border-slate-800">
                        <div>
                            <span class="font-medium text-slate-800 dark:text-slate-100">{{ number_format($payment->amount, 2) }}</span>
                            <span class="text-slate-500 dark:text-slate-400"> via {{ str_replace('_', ' ', $payment->payment_method) }}</span>
                        </div>
                        <span @class([
                            'inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize',
                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300' => $payment->payment_status === 'paid',
                            'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300' => $payment->payment_status === 'partial',
                            'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' => ! in_array($payment->payment_status, ['paid', 'partial']),
                        ])>{{ $payment->payment_status }}</span>
                    </div>
                @empty
                    <p class="text-sm text-slate-400">No payments recorded yet — full payment handling lands in Phase 8.</p>
                @endforelse

                @if ($order->receipt)
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Receipt <strong class="text-slate-700 dark:text-slate-200">{{ $order->receipt->receipt_number }}</strong> generated.</p>
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
</div>
