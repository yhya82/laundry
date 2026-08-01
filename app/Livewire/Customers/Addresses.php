<?php

namespace App\Livewire\Customers;

use App\Models\Customer;
use App\Models\CustomerAddress;
use Livewire\Component;

class Addresses extends Component
{
    public Customer $customer;

    public bool $showDrawer = false;

    public ?CustomerAddress $editing = null;

    public ?string $label = null;

    public ?string $street = null;

    public ?string $area = null;

    public ?string $city = null;

    public ?string $notes = null;

    public bool $is_default = false;

    protected function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:50'],
            'street' => ['nullable', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:150'],
            'city' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:255'],
            'is_default' => ['boolean'],
        ];
    }

    public function create(): void
    {
        $this->reset(['editing', 'label', 'street', 'area', 'city', 'notes', 'is_default']);
        $this->showDrawer = true;
    }

    public function edit(CustomerAddress $address): void
    {
        abort_unless($address->customer_id === $this->customer->id, 403);

        $this->editing = $address;
        $this->label = $address->label;
        $this->street = $address->street;
        $this->area = $address->area;
        $this->city = $address->city;
        $this->notes = $address->notes;
        $this->is_default = $address->is_default;
        $this->showDrawer = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->is_default) {
            $this->customer->addresses()->where('id', '!=', $this->editing?->id)->update(['is_default' => false]);
        }

        if ($this->editing) {
            $this->editing->update($data);
            $this->dispatch('notify', type: 'success', message: 'Address updated.');
        } else {
            $this->customer->addresses()->create($data);
            $this->dispatch('notify', type: 'success', message: 'Address added.');
        }

        $this->closeDrawer();
    }

    public function delete(CustomerAddress $address): void
    {
        abort_unless($address->customer_id === $this->customer->id, 403);

        $address->delete();
        $this->dispatch('notify', type: 'success', message: 'Address removed.');
    }

    public function closeDrawer(): void
    {
        $this->showDrawer = false;
        $this->reset(['editing', 'label', 'street', 'area', 'city', 'notes', 'is_default']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.customers.addresses', [
            'addresses' => $this->customer->addresses()->orderByDesc('is_default')->get(),
        ]);
    }
}
