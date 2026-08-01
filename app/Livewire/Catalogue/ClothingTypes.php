<?php

namespace App\Livewire\Catalogue;

use App\Models\ClothingType;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class ClothingTypes extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showDrawer = false;

    public ?ClothingType $editing = null;

    public string $name = '';

    public ?string $description = null;

    public string $status = 'active';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('clothing_types', 'name')->ignore($this->editing?->id)],
            'description' => ['nullable', 'string', 'max:255'],
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
        $this->status = 'active';
        $this->showDrawer = true;
    }

    public function edit(ClothingType $clothingType): void
    {
        $this->editing = $clothingType;
        $this->name = $clothingType->name;
        $this->description = $clothingType->description;
        $this->status = $clothingType->status;
        $this->showDrawer = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editing) {
            $this->editing->update($data);
            $this->dispatch('notify', type: 'success', message: 'Clothing type updated.');
        } else {
            ClothingType::create($data);
            $this->dispatch('notify', type: 'success', message: 'Clothing type created.');
        }

        $this->closeDrawer();
    }

    public function toggleStatus(ClothingType $clothingType): void
    {
        $clothingType->update(['status' => $clothingType->status === 'active' ? 'inactive' : 'active']);
        $this->dispatch('notify', type: 'success', message: "Clothing type marked {$clothingType->status}.");
    }

    public function closeDrawer(): void
    {
        $this->showDrawer = false;
        $this->reset(['editing', 'name', 'description']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.catalogue.clothing-types', [
            'clothingTypes' => ClothingType::query()
                ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
                ->orderBy('name')
                ->paginate(15),
        ])->title('Clothing Types');
    }
}
