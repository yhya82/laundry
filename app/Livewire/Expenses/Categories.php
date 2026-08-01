<?php

namespace App\Livewire\Expenses;

use App\Models\ExpenseCategory;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class Categories extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showDrawer = false;

    public ?ExpenseCategory $editing = null;

    public string $name = '';

    public ?string $description = null;

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('expense_categories', 'name')->ignore($this->editing?->id)],
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

    public function edit(ExpenseCategory $category): void
    {
        $this->editing = $category;
        $this->name = $category->name;
        $this->description = $category->description;
        $this->showDrawer = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editing) {
            $this->editing->update($data);
            $this->dispatch('notify', type: 'success', message: 'Category updated.');
        } else {
            ExpenseCategory::create($data);
            $this->dispatch('notify', type: 'success', message: 'Category created.');
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
        return view('livewire.expenses.categories', [
            'categories' => ExpenseCategory::query()
                ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
                ->orderBy('name')
                ->paginate(15),
        ])->title('Expense Categories');
    }
}
