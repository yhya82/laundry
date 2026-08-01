<?php

namespace App\Livewire\Payments;

use App\Models\Payment;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $methodFilter = '';

    #[Url]
    public string $search = '';

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingMethodFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.payments.index', [
            'payments' => Payment::query()
                ->with(['customer', 'laundryOrder', 'refunds'])
                ->when($this->statusFilter, fn ($q) => $q->where('payment_status', $this->statusFilter))
                ->when($this->methodFilter, fn ($q) => $q->where('payment_method', $this->methodFilter))
                ->when($this->search, fn ($q) => $q->where(function ($inner) {
                    $inner->where('payment_number', 'like', "%{$this->search}%")
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$this->search}%")
                            ->orWhere('phone', 'like', "%{$this->search}%")
                        );
                }))
                ->latest()
                ->paginate(20),
        ])->title('Payments');
    }
}
