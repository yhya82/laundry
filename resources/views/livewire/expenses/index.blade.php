<div>
    <x-tabs :items="[
        'expenses.index' => 'Expenses',
        'expenses.schedules' => 'Schedules',
        'expenses.categories' => 'Categories',
    ]" />

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-900 dark:text-white">Expenses</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Business expenses, one-off and recurring-generated alike.</p>
        </div>
        @can('expenses.create')
            <button wire:click="openCreateDrawer" type="button" class="rounded-md bg-sky-600 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-700">
                + Record expense
            </button>
        @endcan
    </div>

    <div class="mb-4 flex flex-wrap gap-3">
        <select wire:model.live="statusFilter" class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white">
            <option value="">All statuses</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
            <option value="paid">Paid</option>
            <option value="cancelled">Cancelled</option>
        </select>
        <select wire:model.live="categoryFilter" class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white">
            <option value="">All categories</option>
            @foreach ($this->categories as $category)
                <option value="{{ $category->id }}">{{ $category->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-800">
                <tr>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Title</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Category</th>
                    <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Amount</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Method</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Date</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-700 dark:bg-slate-900">
                @forelse ($expenses as $expense)
                    <tr wire:key="exp-{{ $expense->id }}">
                        <td class="px-4 py-2.5 text-sm font-medium text-slate-800 dark:text-slate-100">
                            {{ $expense->title }}
                            @if ($expense->expense_schedule_id)
                                <span class="ml-1 inline-flex rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-500 dark:bg-slate-800 dark:text-slate-400">recurring</span>
                            @endif
                            @if ($expense->attachment_path)
                                <a href="{{ $this->attachmentUrl($expense->attachment_path) }}" target="_blank" class="ml-1 text-xs text-sky-600 hover:underline dark:text-sky-400">attachment</a>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-sm text-slate-500 dark:text-slate-400">{{ $expense->category->name }}</td>
                        <td class="px-4 py-2.5 text-right text-sm tabular-nums text-slate-800 dark:text-slate-100">{{ number_format($expense->amount, 2) }}</td>
                        <td class="px-4 py-2.5 text-sm capitalize text-slate-500 dark:text-slate-400">{{ str_replace('_', ' ', $expense->payment_method) }}</td>
                        <td class="px-4 py-2.5 text-sm text-slate-500 dark:text-slate-400">{{ $expense->expense_date->format('M j, Y') }}</td>
                        <td class="px-4 py-2.5">
                            <span @class([
                                'inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize',
                                'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300' => in_array($expense->status, ['approved', 'paid']),
                                'bg-rose-100 text-rose-700 dark:bg-rose-900 dark:text-rose-300' => in_array($expense->status, ['rejected', 'cancelled']),
                                'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300' => $expense->status === 'pending',
                            ])>{{ $expense->status }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-right text-sm">
                            @can('expenses.approve')
                                @if ($expense->status === 'pending')
                                    <button wire:click="approve({{ $expense->id }})" class="font-medium text-emerald-600 hover:text-emerald-800 dark:text-emerald-400">Approve</button>
                                    <button wire:click="reject({{ $expense->id }})" class="ml-3 font-medium text-rose-600 hover:text-rose-800 dark:text-rose-400">Reject</button>
                                @endif
                                @if ($expense->status === 'approved')
                                    <button wire:click="markPaid({{ $expense->id }})" class="font-medium text-sky-600 hover:text-sky-800 dark:text-sky-400">Mark paid</button>
                                @endif
                                @if (in_array($expense->status, ['pending', 'approved']))
                                    <button wire:click="cancel({{ $expense->id }})" wire:confirm="Cancel this expense?" class="ml-3 font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400">Cancel</button>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-slate-400">No expenses match this view.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $expenses->links() }}</div>

    <x-drawer :show="$showCreateDrawer" title="Record expense">
        <form wire:submit="save" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Title</label>
                <input type="text" wire:model="title" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                @error('title') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Category</label>
                <select wire:model="category_id" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                    <option value="">Select…</option>
                    @foreach ($this->categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('category_id') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Amount</label>
                <input type="text" inputmode="decimal" wire:model="amount" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                @error('amount') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Payment method</label>
                <select wire:model="payment_method" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                    <option value="cash">Cash</option>
                    <option value="bank_transfer">Bank transfer</option>
                    <option value="mobile_money">Mobile money</option>
                    <option value="card">Card</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Date</label>
                <input type="date" wire:model="expense_date" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                @error('expense_date') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Description (optional)</label>
                <textarea wire:model="description" rows="2" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Receipt/attachment (optional)</label>
                <input type="file" wire:model="attachment" class="mt-1 block w-full text-sm text-slate-600 dark:text-slate-300">
                @error('attachment') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
        </form>

        <x-slot:footer>
            <button wire:click="$set('showCreateDrawer', false)" type="button" class="rounded-md px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700">Cancel</button>
            <button wire:click="save" type="button" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">Save</button>
        </x-slot:footer>
    </x-drawer>
</div>
