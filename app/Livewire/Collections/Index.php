<?php

namespace App\Livewire\Collections;

use App\Models\Collection;
use App\Services\CollectionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $statusFilter = '';

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function generateNow(CollectionService $service): void
    {
        $count = $service->generateDueCollections();
        $this->dispatch('notify', type: 'success', message: "Generated {$count} collection(s) due today.");
    }

    public function convert(int $collectionId, CollectionService $service): void
    {
        $collection = Collection::findOrFail($collectionId);

        try {
            $order = $service->convertToOrder($collection, Auth::user());
        } catch (RuntimeException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());

            return;
        }

        $this->dispatch('notify', type: 'success', message: "Converted to order {$order->order_number}.");
        $this->redirect(route('orders.show', $order), navigate: false);
    }

    public function render()
    {
        return view('livewire.collections.index', [
            'collections' => Collection::query()
                ->with('customer', 'subscription')
                ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
                ->orderBy('scheduled_date')
                ->paginate(20),
        ])->title('Collections');
    }
}
