<?php

namespace App\Livewire\Employees;

use App\Models\Department;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class Departments extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showDrawer = false;

    public ?Department $editing = null;

    public string $name = '';

    public ?string $description = null;

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('departments', 'name')->ignore($this->editing?->id)],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->reset(['editing', 'name', 'description']);
        $this->showDrawer = true;
    }

    public function edit(Department $department): void
    {
        $this->editing = $department;
        $this->name = $department->name;
        $this->description = $department->description;
        $this->showDrawer = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editing) {
            $this->editing->update($data);
            $this->dispatch('notify', type: 'success', message: 'Department updated.');
        } else {
            Department::create($data);
            $this->dispatch('notify', type: 'success', message: 'Department created.');
        }

        $this->closeDrawer();
    }

    public function closeDrawer(): void
    {
        $this->showDrawer = false;
        $this->reset(['editing', 'name', 'description']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.employees.departments', [
            'departments' => Department::query()
                ->withCount('employees')
                ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
                ->orderBy('name')
                ->paginate(15),
        ])->title('Departments');
    }
}
