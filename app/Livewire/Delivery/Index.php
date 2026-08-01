<?php

namespace App\Livewire\Delivery;

use App\Models\Delivery;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $search = '';

    public bool $myDeliveriesOnly = false;

    public function mount(): void
    {
        $user = Auth::user();
        $roleSlugs = $user->roles->pluck('slug');

        // Delivery staff default to their own assignments — everyone else
        // (admin/manager, who also hold deliveries.manage) defaults to the
        // full list. No dedicated "own deliveries only" permission exists,
        // so this is a UX default, not an access boundary — the toggle
        // stays user-adjustable either way.
        $this->myDeliveriesOnly = $roleSlugs->count() === 1 && $roleSlugs->first() === 'delivery-staff';
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingMyDeliveriesOnly(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $employeeId = Auth::user()->employee?->id;

        return view('livewire.delivery.index', [
            'deliveries' => Delivery::query()
                ->with(['customer', 'laundryOrder', 'assignedStaff'])
                ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
                ->when($this->myDeliveriesOnly && $employeeId, fn ($q) => $q->where('assigned_staff_id', $employeeId))
                ->when($this->search, fn ($q) => $q->where(function ($inner) {
                    $inner->where('address_snapshot', 'like', "%{$this->search}%")
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$this->search}%"))
                        ->orWhereHas('laundryOrder', fn ($o) => $o->where('order_number', 'like', "%{$this->search}%"));
                }))
                ->latest()
                ->paginate(20),
        ])->title('Deliveries');
    }
}
