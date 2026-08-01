<?php

namespace App\Livewire\Customers;

use App\Models\Customer;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class Show extends Component
{
    public Customer $customer;

    #[Url]
    public string $tab = 'overview';

    public bool $showEditDrawer = false;

    public string $name = '';

    public string $phone = '';

    public ?string $email = null;

    public string $customer_type = 'walk_in';

    public string $status = 'active';

    /** @var array<int, array{key: string, label: string, ready: bool}> */
    public array $tabs = [
        ['key' => 'overview', 'label' => 'Overview', 'ready' => true],
        ['key' => 'laundry', 'label' => 'Laundry', 'ready' => false],
        ['key' => 'packages', 'label' => 'Packages', 'ready' => false],
        ['key' => 'collections', 'label' => 'Collections', 'ready' => false],
        ['key' => 'payments', 'label' => 'Payments', 'ready' => false],
        ['key' => 'receipts', 'label' => 'Receipts', 'ready' => false],
        ['key' => 'damages', 'label' => 'Damages', 'ready' => false],
        ['key' => 'notifications', 'label' => 'Notifications', 'ready' => false],
        ['key' => 'history', 'label' => 'History', 'ready' => true],
    ];

    public function mount(Customer $customer): void
    {
        $this->customer = $customer;

        if (! in_array($this->tab, array_column($this->tabs, 'key'), true)) {
            $this->tab = 'overview';
        }
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => [
                'required',
                'string',
                'max:30',
                Rule::unique('customers', 'phone')
                    ->ignore($this->customer->id)
                    ->where(fn ($q) => $q->whereNull('deleted_at')),
            ],
            'email' => ['nullable', 'email', 'max:150'],
            'customer_type' => ['required', 'in:walk_in,subscription'],
            'status' => ['required', 'in:active,inactive,suspended'],
        ];
    }

    public function editCustomer(): void
    {
        $this->name = $this->customer->name;
        $this->phone = $this->customer->phone;
        $this->email = $this->customer->email;
        $this->customer_type = $this->customer->customer_type;
        $this->status = $this->customer->status;
        $this->showEditDrawer = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        try {
            $this->customer->update($data);
        } catch (QueryException $e) {
            if ($e->getCode() === '45000' || str_contains($e->getMessage(), 'active customer with this phone')) {
                $this->addError('phone', 'An active customer with this phone number already exists.');

                return;
            }

            throw $e;
        }

        $this->dispatch('notify', type: 'success', message: 'Customer updated.');
        $this->closeDrawer();
    }

    public function closeDrawer(): void
    {
        $this->showEditDrawer = false;
        $this->resetValidation();
    }

    #[Computed]
    public function timeline()
    {
        return $this->customer->timelineEvents()->orderByDesc('occurred_at')->limit(50)->get();
    }

    public function render()
    {
        return view('livewire.customers.show')->title($this->customer->name);
    }
}
