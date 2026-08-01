<div>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-lg font-semibold text-slate-900 dark:text-white">Delivery — {{ $delivery->laundryOrder->order_number }}</h1>
                <span @class([
                    'inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize',
                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300' => $delivery->status === 'delivered',
                    'bg-rose-100 text-rose-700 dark:bg-rose-900 dark:text-rose-300' => in_array($delivery->status, ['failed', 'cancelled']),
                    'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' => ! in_array($delivery->status, ['delivered', 'failed', 'cancelled']),
                ])>{{ str_replace('_', ' ', $delivery->status) }}</span>
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                <a href="{{ route('customers.show', $delivery->customer) }}" class="hover:text-sky-600 dark:hover:text-sky-400">{{ $delivery->customer->name }}</a>
                · Order <a href="{{ route('orders.show', $delivery->laundryOrder) }}" class="hover:text-sky-600 dark:hover:text-sky-400">{{ $delivery->laundryOrder->order_number }}</a>
            </p>
        </div>

        @can('deliveries.manage')
            <div class="flex flex-wrap items-center gap-2">
                @if ($delivery->status === 'pending')
                    <button wire:click="$set('showScheduleDrawer', true)" type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">Set date</button>
                @endif
                @if (in_array($delivery->status, ['pending', 'scheduled']))
                    <button wire:click="$set('showAssignDrawer', true)" type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">Assign staff</button>
                @endif
                @if ($delivery->status === 'assigned')
                    <button wire:click="markPickedUp" type="button" class="rounded-md bg-sky-600 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-700">Mark picked up</button>
                @endif
                @if ($delivery->status === 'picked_up')
                    <button wire:click="markOutForDelivery" type="button" class="rounded-md bg-sky-600 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-700">Mark out for delivery</button>
                @endif
                @if ($delivery->status === 'out_for_delivery')
                    <button wire:click="markDelivered" wire:confirm="Mark this delivery as delivered?" type="button" class="rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Mark delivered</button>
                    <button wire:click="$set('showFailDrawer', true)" type="button" class="rounded-md border border-rose-300 px-3 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50 dark:border-rose-800 dark:text-rose-400 dark:hover:bg-rose-950">Mark failed</button>
                @endif
                @if ($delivery->status === 'failed')
                    <button wire:click="$set('showRescheduleDrawer', true)" type="button" class="rounded-md bg-sky-600 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-700">Reschedule</button>
                @endif
                @if (in_array($delivery->status, ['pending', 'scheduled', 'assigned', 'picked_up', 'out_for_delivery', 'failed']))
                    <button wire:click="$set('showCancelDrawer', true)" type="button" class="rounded-md border border-rose-300 px-3 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50 dark:border-rose-800 dark:text-rose-400 dark:hover:bg-rose-950">Cancel</button>
                @endif
            </div>
        @endcan
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Details</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500 dark:text-slate-400">Address</dt><dd class="text-right text-slate-800 dark:text-slate-100">{{ $delivery->address_snapshot ?: '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500 dark:text-slate-400">Fee</dt><dd class="tabular-nums text-slate-800 dark:text-slate-100">{{ number_format($delivery->delivery_fee, 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500 dark:text-slate-400">Scheduled</dt><dd class="text-slate-800 dark:text-slate-100">{{ $delivery->scheduled_date?->format('M j, Y') ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500 dark:text-slate-400">Assigned staff</dt><dd class="text-slate-800 dark:text-slate-100">{{ $delivery->assignedStaff?->name ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500 dark:text-slate-400">Completed</dt><dd class="text-slate-800 dark:text-slate-100">{{ $delivery->completed_date?->format('M j, Y') ?? '—' }}</dd></div>
                    @if ($delivery->delivery_instructions)
                        <div class="flex justify-between"><dt class="text-slate-500 dark:text-slate-400">Instructions</dt><dd class="text-right text-slate-800 dark:text-slate-100">{{ $delivery->delivery_instructions }}</dd></div>
                    @endif
                    @if ($delivery->failure_reason)
                        <div class="flex justify-between"><dt class="text-slate-500 dark:text-slate-400">Failure reason</dt><dd class="text-right text-rose-600 dark:text-rose-400">{{ $delivery->failure_reason }}</dd></div>
                    @endif
                </dl>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Status history</h2>
                <div class="space-y-2">
                    @foreach ($delivery->statusHistory->sortByDesc('created_at') as $history)
                        <div class="text-xs">
                            <span class="font-medium capitalize text-slate-700 dark:text-slate-200">{{ str_replace('_', ' ', $history->status) }}</span>
                            <span class="text-slate-400"> — {{ $history->created_at->format('M j, g:ia') }}{{ $history->changedBy ? ' by '.$history->changedBy->name : '' }}</span>
                            @if ($history->notes)
                                <p class="text-slate-500 dark:text-slate-400">{{ $history->notes }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <x-drawer :show="$showScheduleDrawer" title="Set delivery date">
        <form wire:submit="schedule" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Date</label>
                <input type="date" wire:model="scheduleDate" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                @error('scheduleDate') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
        </form>
        <x-slot:footer>
            <button wire:click="$set('showScheduleDrawer', false)" type="button" class="rounded-md px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700">Cancel</button>
            <button wire:click="schedule" type="button" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">Save</button>
        </x-slot:footer>
    </x-drawer>

    <x-drawer :show="$showAssignDrawer" title="Assign staff">
        <form wire:submit="assignStaff" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Employee</label>
                <select wire:model="assignEmployeeId" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                    <option value="">Select…</option>
                    @foreach ($this->employees as $employee)
                        <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                    @endforeach
                </select>
                @error('assignEmployeeId') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
        </form>
        <x-slot:footer>
            <button wire:click="$set('showAssignDrawer', false)" type="button" class="rounded-md px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700">Cancel</button>
            <button wire:click="assignStaff" type="button" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">Assign</button>
        </x-slot:footer>
    </x-drawer>

    <x-drawer :show="$showFailDrawer" title="Mark delivery failed">
        <form wire:submit="markFailed" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Reason</label>
                <textarea wire:model="failureReason" rows="3" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white"></textarea>
                @error('failureReason') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
        </form>
        <x-slot:footer>
            <button wire:click="$set('showFailDrawer', false)" type="button" class="rounded-md px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700">Back</button>
            <button wire:click="markFailed" type="button" class="rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Mark failed</button>
        </x-slot:footer>
    </x-drawer>

    <x-drawer :show="$showRescheduleDrawer" title="Reschedule delivery">
        <form wire:submit="reschedule" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">New date</label>
                <input type="date" wire:model="rescheduleDate" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                @error('rescheduleDate') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
        </form>
        <x-slot:footer>
            <button wire:click="$set('showRescheduleDrawer', false)" type="button" class="rounded-md px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700">Cancel</button>
            <button wire:click="reschedule" type="button" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">Reschedule</button>
        </x-slot:footer>
    </x-drawer>

    <x-drawer :show="$showCancelDrawer" title="Cancel delivery">
        <form wire:submit="cancel" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Reason</label>
                <textarea wire:model="cancelReason" rows="3" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white"></textarea>
                @error('cancelReason') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
        </form>
        <x-slot:footer>
            <button wire:click="$set('showCancelDrawer', false)" type="button" class="rounded-md px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700">Back</button>
            <button wire:click="cancel" type="button" class="rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Cancel delivery</button>
        </x-slot:footer>
    </x-drawer>
</div>
