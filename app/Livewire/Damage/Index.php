<?php

namespace App\Livewire\Damage;

use App\Models\DamageReport;
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

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.damage.index', [
            'reports' => DamageReport::query()
                ->with(['customer', 'laundryOrder'])
                ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
                ->when($this->search, fn ($q) => $q->where(function ($inner) {
                    $inner->where('description', 'like', "%{$this->search}%")
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$this->search}%"))
                        ->orWhereHas('laundryOrder', fn ($o) => $o->where('order_number', 'like', "%{$this->search}%"));
                }))
                ->latest()
                ->paginate(20),
        ])->title('Damage Reports');
    }
}
