<div>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-lg font-semibold text-slate-900 dark:text-white">Damage report #{{ $report->id }}</h1>
                <span @class([
                    'inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize',
                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300' => $report->status === 'resolved',
                    'bg-rose-100 text-rose-700 dark:bg-rose-900 dark:text-rose-300' => $report->status === 'rejected',
                    'bg-sky-100 text-sky-700 dark:bg-sky-900 dark:text-sky-300' => $report->status === 'approved',
                    'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' => in_array($report->status, ['reported', 'under_review']),
                ])>{{ str_replace('_', ' ', $report->status) }}</span>
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                <a href="{{ route('customers.show', $report->customer) }}" class="hover:text-sky-600 dark:hover:text-sky-400">{{ $report->customer->name }}</a>
                · Order <a href="{{ route('orders.show', $report->laundryOrder) }}" class="hover:text-sky-600 dark:hover:text-sky-400">{{ $report->laundryOrder->order_number }}</a>
                · filed {{ $report->created_at->diffForHumans() }} by {{ $report->createdBy->name }}
            </p>
        </div>

        @can('damage.approve')
            <div class="flex items-center gap-2">
                @if (in_array($report->status, ['reported', 'under_review']))
                    @if ($report->status === 'reported')
                        <button wire:click="markUnderReview" type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">
                            Start review
                        </button>
                    @endif
                    <button wire:click="reject" wire:confirm="Reject this damage report? This closes it with no compensation." type="button" class="rounded-md border border-rose-300 px-3 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50 dark:border-rose-800 dark:text-rose-400 dark:hover:bg-rose-950">
                        Reject
                    </button>
                    <button wire:click="openResolveDrawer" type="button" class="rounded-md bg-sky-600 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-700">
                        Set resolution
                    </button>
                @elseif ($report->status === 'approved')
                    <button wire:click="openResolveDrawer" type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">
                        Edit resolution
                    </button>
                    <button wire:click="resolve" wire:confirm="Resolve this report and disburse compensation? This locks the resolution permanently." type="button" class="rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                        Resolve &amp; disburse
                    </button>
                @endif
            </div>
        @endcan
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-200">Description</h2>
                <p class="text-sm text-slate-600 dark:text-slate-300">{{ $report->description }}</p>
            </div>

            <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Affected items</h2>
                <div class="space-y-2">
                    @foreach ($report->items as $item)
                        <div class="flex items-center justify-between rounded-md border border-slate-100 p-2.5 text-sm dark:border-slate-800">
                            <div>
                                <span class="font-medium text-slate-800 dark:text-slate-100">{{ $item->orderItem->clothingType->name }}</span>
                                <span class="text-slate-500 dark:text-slate-400"> — {{ $item->damageType->name }}</span>
                                @if ($item->description)
                                    <p class="text-xs text-slate-400">{{ $item->description }}</p>
                                @endif
                            </div>
                            <span @class([
                                'inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize',
                                'bg-rose-100 text-rose-700 dark:bg-rose-900 dark:text-rose-300' => in_array($item->severity, ['high', 'critical']),
                                'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300' => $item->severity === 'medium',
                                'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' => $item->severity === 'low',
                            ])>{{ $item->severity }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Evidence</h2>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @foreach ($report->evidence as $file)
                        <div wire:key="ev-{{ $file->id }}" class="group relative overflow-hidden rounded-md border border-slate-200 dark:border-slate-700">
                            <a href="{{ \Illuminate\Support\Facades\Storage::url($file->file_path) }}" target="_blank">
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($file->file_path) }}" alt="Evidence" class="h-28 w-full object-cover">
                            </a>
                            @can('damage.approve')
                                @if ($report->status !== 'resolved')
                                    <button wire:click="deleteEvidence({{ $file->id }})" wire:confirm="Remove this evidence photo?" type="button" class="absolute right-1 top-1 hidden rounded-full bg-black/60 px-1.5 py-0.5 text-xs text-white group-hover:block">✕</button>
                                @endif
                            @endcan
                        </div>
                    @endforeach
                    @if ($report->evidence->isEmpty())
                        <p class="col-span-full text-sm text-slate-400">No evidence photos uploaded.</p>
                    @endif
                </div>

                @can('damage.create')
                    @if ($report->status !== 'resolved' && $report->status !== 'rejected')
                        <form wire:submit="addEvidence" class="mt-3 flex items-center gap-2">
                            <input type="file" wire:model="newEvidence" multiple accept="image/*" class="block w-full text-sm text-slate-600 dark:text-slate-300">
                            <button type="submit" class="shrink-0 rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">Upload</button>
                        </form>
                        @error('newEvidence.*') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    @endif
                @endcan
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Resolution</h2>
                @if ($report->resolution_type)
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-slate-500 dark:text-slate-400">Type</dt><dd class="font-medium capitalize text-slate-800 dark:text-slate-100">{{ str_replace('_', ' ', $report->resolution_type) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500 dark:text-slate-400">Cash</dt><dd class="tabular-nums text-slate-800 dark:text-slate-100">{{ number_format($report->cash_compensation_amount, 2) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500 dark:text-slate-400">Store credit</dt><dd class="tabular-nums text-slate-800 dark:text-slate-100">{{ number_format($report->store_credit_compensation_amount, 2) }}</dd></div>
                        @if ($report->approvedBy)
                            <div class="flex justify-between"><dt class="text-slate-500 dark:text-slate-400">By</dt><dd class="text-slate-800 dark:text-slate-100">{{ $report->approvedBy->name }}</dd></div>
                        @endif
                    </dl>
                @else
                    <p class="text-sm text-slate-400">No resolution set yet.</p>
                @endif
            </div>
        </div>
    </div>

    <x-drawer :show="$showResolveDrawer" title="Set resolution">
        <form wire:submit="approve" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Resolution type</label>
                <select wire:model="resolutionType" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                    <option value="repair">Repair</option>
                    <option value="refund">Refund</option>
                    <option value="rewash">Rewash</option>
                    <option value="store_credit">Store credit</option>
                    <option value="replacement">Replacement</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Cash compensation</label>
                <input type="text" inputmode="decimal" wire:model="cashAmount" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                @error('cashAmount') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Store credit compensation</label>
                <input type="text" inputmode="decimal" wire:model="creditAmount" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                @error('creditAmount') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                <p class="mt-1 text-xs text-slate-400">Store credit is only actually credited to the customer once you "Resolve &amp; disburse" — setting it here just records the plan.</p>
            </div>
        </form>

        <x-slot:footer>
            <button wire:click="$set('showResolveDrawer', false)" type="button" class="rounded-md px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700">Cancel</button>
            <button wire:click="approve" type="button" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">Save resolution</button>
        </x-slot:footer>
    </x-drawer>
</div>
