<?php

namespace App\Livewire\Catalogue;

use App\Models\Service;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class Services extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showDrawer = false;

    public ?Service $editing = null;

    public string $name = '';

    public ?string $description = null;

    public string $price = '0.00';

    public string $status = 'active';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('services', 'name')->ignore($this->editing?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->reset(['editing', 'name', 'description']);
        $this->price = '0.00';
        $this->status = 'active';
        $this->showDrawer = true;
    }

    public function edit(Service $service): void
    {
        $this->editing = $service;
        $this->name = $service->name;
        $this->description = $service->description;
        $this->price = (string) $service->price;
        $this->status = $service->status;
        $this->showDrawer = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editing) {
            $this->editing->update($data);
            $this->dispatch('notify', type: 'success', message: 'Service updated.');
        } else {
            Service::create($data);
            $this->dispatch('notify', type: 'success', message: 'Service created.');
        }

        $this->closeDrawer();
    }

    public function toggleStatus(Service $service): void
    {
        $service->update(['status' => $service->status === 'active' ? 'inactive' : 'active']);
        $this->dispatch('notify', type: 'success', message: "Service marked {$service->status}.");
    }

    public function closeDrawer(): void
    {
        $this->showDrawer = false;
        $this->reset(['editing', 'name', 'description']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.catalogue.services', [
            'services' => Service::query()
                ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
                ->orderBy('name')
                ->paginate(15),
        ])->title('Services');
    }
}
