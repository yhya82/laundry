<?php

namespace App\Livewire\Catalogue;

use App\Models\Package;
use App\Models\Service;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Packages extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showDrawer = false;

    public ?Package $editing = null;

    public string $name = '';

    public ?string $description = null;

    public string $price = '0.00';

    public string $maximum_clothes = '10';

    public string $priority = 'normal';

    public string $status = 'active';

    /** @var array<int> */
    public array $selectedServices = [];

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'maximum_clothes' => ['required', 'integer', 'min:1'],
            'priority' => ['required', 'in:normal,express'],
            'status' => ['required', 'in:active,inactive'],
            'selectedServices' => ['array'],
            'selectedServices.*' => ['integer', 'exists:services,id'],
        ];
    }

    #[Computed]
    public function services()
    {
        return Service::where('status', 'active')->orderBy('name')->get();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->reset(['editing', 'name', 'description', 'selectedServices']);
        $this->price = '0.00';
        $this->maximum_clothes = '10';
        $this->priority = 'normal';
        $this->status = 'active';
        $this->showDrawer = true;
    }

    public function edit(Package $package): void
    {
        $this->editing = $package;
        $this->name = $package->name;
        $this->description = $package->description;
        $this->price = (string) $package->price;
        $this->maximum_clothes = (string) $package->maximum_clothes;
        $this->priority = $package->priority;
        $this->status = $package->status;
        $this->selectedServices = $package->services()->pluck('services.id')->all();
        $this->showDrawer = true;
    }

    public function save(): void
    {
        $data = $this->validate();
        $serviceIds = $data['selectedServices'];
        unset($data['selectedServices']);

        if ($this->editing) {
            $this->editing->update($data);
            $this->editing->services()->sync($serviceIds);
            $this->dispatch('notify', type: 'success', message: 'Package updated.');
        } else {
            $package = Package::create($data);
            $package->services()->sync($serviceIds);
            $this->dispatch('notify', type: 'success', message: 'Package created.');
        }

        $this->closeDrawer();
    }

    public function toggleStatus(Package $package): void
    {
        $package->update(['status' => $package->status === 'active' ? 'inactive' : 'active']);
        $this->dispatch('notify', type: 'success', message: "Package marked {$package->status}.");
    }

    public function closeDrawer(): void
    {
        $this->showDrawer = false;
        $this->reset(['editing', 'name', 'description', 'selectedServices']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.catalogue.packages', [
            'packages' => Package::query()
                ->withCount('services')
                ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
                ->orderBy('name')
                ->paginate(15),
        ])->title('Packages');
    }
}
