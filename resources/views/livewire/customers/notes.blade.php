<div>
    <div class="mb-3 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Notes</h3>
        @can('customers.update')
            <button wire:click="create" type="button" class="text-sm font-medium text-sky-600 hover:text-sky-800 dark:text-sky-400">+ Add note</button>
        @endcan
    </div>

    <div class="space-y-2">
        @forelse ($notes as $note)
            <div wire:key="note-{{ $note->id }}" class="rounded-md border border-slate-200 p-3 text-sm dark:border-slate-700">
                <div class="flex items-center justify-between">
                    <span @class([
                        'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                        'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300' => $note->note_type === 'instruction',
                        'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' => $note->note_type === 'internal',
                    ])>
                        {{ $note->note_type === 'instruction' ? 'Customer instruction' : 'Internal note' }}
                    </span>
                    <span class="text-xs text-slate-400">{{ $note->created_at->diffForHumans() }}{{ $note->author ? ' · '.$note->author->name : '' }}</span>
                </div>
                <p class="mt-2 whitespace-pre-line text-slate-700 dark:text-slate-200">{{ $note->content }}</p>
            </div>
        @empty
            <p class="text-sm text-slate-400">No notes yet.</p>
        @endforelse
    </div>

    <x-drawer :show="$showDrawer" title="New note">
        <form wire:submit="save" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Type</label>
                <select wire:model="note_type" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                    <option value="instruction">Customer instruction (visible to customer-facing staff)</option>
                    <option value="internal">Internal note (staff-only)</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Content</label>
                <textarea wire:model="content" rows="4" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white"></textarea>
                @error('content') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
        </form>

        <x-slot:footer>
            <button wire:click="closeDrawer" type="button" class="rounded-md px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700">Cancel</button>
            <button wire:click="save" type="button" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">Save</button>
        </x-slot:footer>
    </x-drawer>
</div>
