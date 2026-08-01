<?php

namespace App\Livewire\Discounts;

use App\Models\DiscountTemplate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class Templates extends Component
{
    use WithPagination;

    public bool $showDrawer = false;

    public ?DiscountTemplate $editing = null;

    public string $name = '';

    public string $discount_type = 'percentage';

    public string $value = '0';

    public bool $requires_approval = false;

    public ?string $max_cashier_value = null;

    public string $status = 'active';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('discount_templates', 'name')->ignore($this->editing?->id)],
            'discount_type' => ['required', 'in:percentage,fixed'],
            'value' => ['required', 'numeric', 'min:0'],
            'requires_approval' => ['boolean'],
            'max_cashier_value' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }

    public function create(): void
    {
        $this->reset(['editing', 'name', 'max_cashier_value']);
        $this->discount_type = 'percentage';
        $this->value = '0';
        $this->requires_approval = false;
        $this->status = 'active';
        $this->showDrawer = true;
    }

    public function edit(DiscountTemplate $discountTemplate): void
    {
        $this->editing = $discountTemplate;
        $this->name = $discountTemplate->name;
        $this->discount_type = $discountTemplate->discount_type;
        $this->value = (string) $discountTemplate->value;
        $this->requires_approval = $discountTemplate->requires_approval;
        $this->max_cashier_value = $discountTemplate->max_cashier_value !== null ? (string) $discountTemplate->max_cashier_value : null;
        $this->status = $discountTemplate->status;
        $this->showDrawer = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editing) {
            $this->editing->update($data);
            $this->dispatch('notify', type: 'success', message: 'Discount template updated.');
        } else {
            DiscountTemplate::create($data);
            $this->dispatch('notify', type: 'success', message: 'Discount template created.');
        }

        $this->closeDrawer();
    }

    public function toggleStatus(DiscountTemplate $discountTemplate): void
    {
        $discountTemplate->update(['status' => $discountTemplate->status === 'active' ? 'inactive' : 'active']);
    }

    public function closeDrawer(): void
    {
        $this->showDrawer = false;
        $this->reset(['editing', 'name', 'max_cashier_value']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.discounts.templates', [
            'templates' => DiscountTemplate::orderBy('name')->paginate(15),
        ])->title('Discount Templates');
    }
}
