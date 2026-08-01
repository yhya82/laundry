<?php

use Livewire\Volt\Component;

new class extends Component
{
    public int $count = 0;

    public function increment(): void
    {
        $this->count++;
    }
}; ?>

<div class="mx-auto max-w-sm rounded-xl border border-emerald-700/40 bg-emerald-950/5 p-6 text-center">
    <p class="text-sm uppercase tracking-widest text-emerald-700">Phase 1 smoke test</p>
    <p class="mt-2 text-3xl font-bold">{{ $count }}</p>
    <button
        type="button"
        wire:click="increment"
        class="mt-4 rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800"
    >
        Increment
    </button>
</div>
