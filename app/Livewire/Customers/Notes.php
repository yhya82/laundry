<?php

namespace App\Livewire\Customers;

use App\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Notes extends Component
{
    public Customer $customer;

    public bool $showDrawer = false;

    public string $note_type = 'instruction';

    public string $content = '';

    protected function rules(): array
    {
        return [
            'note_type' => ['required', 'in:instruction,internal'],
            'content' => ['required', 'string', 'max:2000'],
        ];
    }

    public function create(): void
    {
        $this->reset(['content']);
        $this->note_type = 'instruction';
        $this->showDrawer = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        $this->customer->notes()->create([
            ...$data,
            'created_by' => Auth::id(),
        ]);

        $this->dispatch('notify', type: 'success', message: 'Note added.');
        $this->closeDrawer();
    }

    public function closeDrawer(): void
    {
        $this->showDrawer = false;
        $this->reset(['content']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.customers.notes', [
            'notes' => $this->customer->notes()->with('author')->latest()->get(),
        ]);
    }
}
