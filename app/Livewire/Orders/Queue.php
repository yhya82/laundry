<?php

namespace App\Livewire\Orders;

use App\Models\LaundryOrder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Queue extends Component
{
    use WithPagination;

    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $priorityFilter = '';

    #[Url]
    public string $search = '';

    /** Non-terminal stages, in processing order — the default "active queue" view. */
    private const ACTIVE_STAGES = ['waiting_queue', 'received', 'sorting', 'washing', 'drying', 'ironing', 'quality_check', 'packaging', 'ready'];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        // Sorted by (status, priority, created_at) — matching
        // idx_laundry_orders_queue_sort exactly, leading column first, so
        // the composite index is actually usable (confirmed via EXPLAIN
        // during Phase 6 verification, not just "results look right").
        $query = LaundryOrder::query()
            ->with('customer')
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when(! $this->statusFilter, fn ($q) => $q->whereIn('status', self::ACTIVE_STAGES))
            ->when($this->priorityFilter, fn ($q) => $q->where('priority', $this->priorityFilter))
            ->when($this->search, fn ($q) => $q->where(fn ($sub) => $sub
                ->where('order_number', 'like', "%{$this->search}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$this->search}%"))
            ))
            // Plain ascending order — 'express' < 'normal' alphabetically,
            // which happens to be the wanted priority order too, so this
            // stays sargable against idx_laundry_orders_queue_sort instead
            // of needing a FIELD()/CASE expression that would force a filesort.
            ->orderBy('status')
            ->orderBy('priority')
            ->orderBy('created_at');

        return view('livewire.orders.queue', [
            'orders' => $query->paginate(20),
        ])->title('Laundry Orders');
    }
}
