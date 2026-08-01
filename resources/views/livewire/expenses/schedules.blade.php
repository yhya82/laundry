<div>
    <x-tabs :items="[
        'expenses.index' => 'Expenses',
        'expenses.schedules' => 'Schedules',
        'expenses.categories' => 'Categories',
    ]" />

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-900 dark:text-white">Recurring Schedules</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Standing commitments — rent, salaries, and the like — generated automatically on their next run date.</p>
        </div>
        @can('expenses.create')
            <button wire:click="create" type="button" class="rounded-md bg-sky-600 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-700">
                + New schedule
            </button>
        @endcan
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-800">
                <tr>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Title</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Category</th>
                    <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Amount</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Frequency</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Next run</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-700 dark:bg-slate-900">
                @forelse ($schedules as $schedule)
                    <tr wire:key="sched-{{ $schedule->id }}">
                        <td class="px-4 py-2.5 text-sm font-medium text-slate-800 dark:text-slate-100">{{ $schedule->title }}</td>
                        <td class="px-4 py-2.5 text-sm text-slate-500 dark:text-slate-400">{{ $schedule->category->name }}</td>
                        <td class="px-4 py-2.5 text-right text-sm tabular-nums text-slate-800 dark:text-slate-100">{{ number_format($schedule->amount, 2) }}</td>
                        <td class="px-4 py-2.5 text-sm capitalize text-slate-500 dark:text-slate-400">{{ $schedule->frequency }}</td>
                        <td class="px-4 py-2.5 text-sm text-slate-500 dark:text-slate-400">{{ $schedule->next_run_date->format('M j, Y') }}</td>
                        <td class="px-4 py-2.5">
                            <span @class([
                                'inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize',
                                'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300' => $schedule->status === 'active',
                                'bg-rose-100 text-rose-700 dark:bg-rose-900 dark:text-rose-300' => $schedule->status === 'cancelled',
                                'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' => $schedule->status === 'paused',
                            ])>{{ $schedule->status }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-right text-sm">
                            @can('expenses.create')
                                @if ($schedule->status !== 'cancelled')
                                    <button wire:click="edit({{ $schedule->id }})" class="font-medium text-sky-600 hover:text-sky-800 dark:text-sky-400">Edit</button>
                                    <button wire:click="togglePause({{ $schedule->id }})" class="ml-3 font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400">
                                        {{ $schedule->status === 'active' ? 'Pause' : 'Resume' }}
                                    </button>
                                    <button wire:click="cancelSchedule({{ $schedule->id }})" wire:confirm="Cancel this recurring schedule? This cannot be undone." class="ml-3 font-medium text-rose-600 hover:text-rose-800 dark:text-rose-400">
                                        Cancel
                                    </button>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-slate-400">No recurring schedules yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $schedules->links() }}</div>

    <x-drawer :show="$showDrawer" :title="$editing ? 'Edit schedule' : 'New schedule'">
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
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Frequency</label>
                <select wire:model="frequency" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                    <option value="quarterly">Quarterly</option>
                    <option value="yearly">Yearly</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Next run date</label>
                <input type="date" wire:model="next_run_date" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                @error('next_run_date') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
        </form>

        <x-slot:footer>
            <button wire:click="closeDrawer" type="button" class="rounded-md px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700">Cancel</button>
            <button wire:click="save" type="button" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">Save</button>
        </x-slot:footer>
    </x-drawer>
</div>
