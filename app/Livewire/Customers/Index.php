<?php

namespace App\Livewire\Customers;

use App\Models\Customer;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $typeFilter = '';

    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $sortBy = 'name';

    #[Url]
    public string $sortDirection = 'asc';

    public bool $showDrawer = false;

    public ?Customer $editing = null;

    public string $name = '';

    public string $phone = '';

    public ?string $email = null;

    public string $customer_type = 'walk_in';

    public string $status = 'active';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => [
                'required',
                'string',
                'max:30',
                Rule::unique('customers', 'phone')
                    ->ignore($this->editing?->id)
                    ->where(fn ($q) => $q->whereNull('deleted_at')),
            ],
            'email' => ['nullable', 'email', 'max:150'],
            'customer_type' => ['required', 'in:walk_in,subscription'],
            'status' => ['required', 'in:active,inactive,suspended'],
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function create(): void
    {
        $this->reset(['editing', 'name', 'phone', 'email']);
        $this->customer_type = 'walk_in';
        $this->status = 'active';
        $this->showDrawer = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        try {
            if ($this->editing) {
                $this->editing->update($data);
                $this->dispatch('notify', type: 'success', message: 'Customer updated.');
            } else {
                Customer::create($data);
                $this->dispatch('notify', type: 'success', message: 'Customer created.');
            }
        } catch (QueryException $e) {
            // Backstop for a race condition between the app-level uniqueness
            // check above and trg_customers_phone_unique_active_ins/upd —
            // the trigger remains the actual authority, this just keeps the
            // failure user-legible instead of a raw SQLSTATE dump.
            if ($e->getCode() === '45000' || str_contains($e->getMessage(), 'active customer with this phone')) {
                $this->addError('phone', 'An active customer with this phone number already exists.');

                return;
            }

            throw $e;
        }

        $this->closeDrawer();
    }

    public function closeDrawer(): void
    {
        $this->showDrawer = false;
        $this->reset(['editing', 'name', 'phone', 'email']);
        $this->resetValidation();
    }

    public function render()
    {
        $query = Customer::query()
            ->when($this->typeFilter, fn ($q) => $q->where('customer_type', $this->typeFilter))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter));

        if ($this->search !== '') {
            // Simple LIKE search covers the common "type a few letters /
            // digits" case correctly; ftx_customers_name (the FULLTEXT
            // index already in schema.sql) is available for a true
            // contains-search upgrade once search volume justifies the
            // extra query complexity — not needed to meet §3.7 here.
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "{$this->search}%")
            );
        }

        $query->orderBy($this->sortBy, $this->sortDirection);

        return view('livewire.customers.index', [
            'customers' => $query->paginate(15),
        ])->title('Customers');
    }
}
