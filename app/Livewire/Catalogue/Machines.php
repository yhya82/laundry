<?php

namespace App\Livewire\Catalogue;

use App\Models\Machine;
use Livewire\Component;
use Livewire\WithPagination;

class Machines extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showDrawer = false;

    public ?Machine $editing = null;

    public string $name = '';

    public string $capacity = '1';

    public string $status = 'available';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'capacity' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:available,running,maintenance,inactive'],
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->reset(['editing', 'name']);
        $this->capacity = '1';
        $this->status = 'available';
        $this->showDrawer = true;
    }

    public function edit(Machine $machine): void
    {
        $this->editing = $machine;
        $this->name = $machine->name;
        $this->capacity = (string) $machine->capacity;
        $this->status = $machine->status;
        $this->showDrawer = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editing) {
            $this->editing->update($data);
            $this->dispatch('notify', type: 'success', message: 'Machine updated.');
        } else {
            Machine::create($data);
            $this->dispatch('notify', type: 'success', message: 'Machine created.');
        }

        $this->closeDrawer();
    }

    public function closeDrawer(): void
    {
        $this->showDrawer = false;
        $this->reset(['editing', 'name']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.catalogue.machines', [
            'machines' => Machine::query()
                ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
                ->orderBy('name')
                ->paginate(15),
        ])->title('Machines');
    }
}
