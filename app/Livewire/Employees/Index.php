<?php

namespace App\Livewire\Employees;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showDrawer = false;

    public ?Employee $editing = null;

    public string $name = '';

    public ?string $phone = null;

    public ?string $address = null;

    public ?string $position = null;

    public ?int $department_id = null;

    public string $status = 'active';

    public ?string $joined_date = null;

    public ?int $user_id = null;

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'status' => ['required', 'in:active,inactive,suspended,terminated'],
            'joined_date' => ['nullable', 'date'],
            'user_id' => ['nullable', 'exists:users,id'],
        ];
    }

    #[Computed]
    public function departments()
    {
        return Department::orderBy('name')->get();
    }

    /**
     * Only users not already linked to another employee (uq_employees_user)
     * — plus the currently-edited employee's own linked user, if any, so it
     * still appears as a selectable option.
     */
    #[Computed]
    public function availableUsers()
    {
        return User::whereDoesntHave('employee')
            ->orWhere('id', $this->editing?->user_id)
            ->orderBy('name')
            ->get();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->reset(['editing', 'name', 'phone', 'address', 'position', 'department_id', 'joined_date', 'user_id']);
        $this->status = 'active';
        $this->showDrawer = true;
    }

    public function edit(Employee $employee): void
    {
        $this->editing = $employee;
        $this->name = $employee->name;
        $this->phone = $employee->phone;
        $this->address = $employee->address;
        $this->position = $employee->position;
        $this->department_id = $employee->department_id;
        $this->status = $employee->status;
        $this->joined_date = $employee->joined_date?->format('Y-m-d');
        $this->user_id = $employee->user_id;
        $this->showDrawer = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editing) {
            $this->editing->update($data);
            $this->dispatch('notify', type: 'success', message: 'Employee updated.');
        } else {
            Employee::create($data);
            $this->dispatch('notify', type: 'success', message: 'Employee created.');
        }

        $this->closeDrawer();
    }

    public function closeDrawer(): void
    {
        $this->showDrawer = false;
        $this->reset(['editing', 'name', 'phone', 'address', 'position', 'department_id', 'joined_date', 'user_id']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.employees.index', [
            'employees' => Employee::query()
                ->with('department', 'user')
                ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
                ->orderBy('name')
                ->paginate(15),
        ])->title('Employees');
    }
}
