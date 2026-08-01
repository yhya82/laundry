<?php

namespace App\Livewire\Subscriptions;

use App\Models\Customer;
use App\Models\Package;
use App\Models\Subscription;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public bool $showDrawer = false;

    public ?Subscription $editing = null;

    public ?int $customer_id = null;

    public string $start_date = '';

    public string $frequency_type = 'monthly_1';

    public string $custom_interval_days = '30';

    public string $status = 'active';

    /** @var array<int, array{package_id: string, quantity: string}> */
    public array $packageLines = [];

    protected function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'start_date' => ['required', 'date'],
            'frequency_type' => ['required', 'in:monthly_1,monthly_2,monthly_3,monthly_4,custom'],
            'custom_interval_days' => ['required_if:frequency_type,custom', 'nullable', 'integer', 'min:1'],
            'status' => ['required', 'in:active,paused,suspended,expired,cancelled'],
            'packageLines' => ['required', 'array', 'min:1'],
            'packageLines.*.package_id' => ['required', 'exists:packages,id'],
            'packageLines.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    #[Computed]
    public function customers()
    {
        return Customer::where('status', 'active')->orderBy('name')->get();
    }

    #[Computed]
    public function packages()
    {
        return Package::where('status', 'active')->orderBy('name')->get();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->reset(['editing', 'customer_id']);
        $this->start_date = now()->toDateString();
        $this->frequency_type = 'monthly_1';
        $this->custom_interval_days = '30';
        $this->status = 'active';
        $this->packageLines = [['package_id' => '', 'quantity' => '1']];
        $this->showDrawer = true;
    }

    public function edit(Subscription $subscription): void
    {
        $this->editing = $subscription;
        $this->customer_id = $subscription->customer_id;
        $this->start_date = $subscription->start_date->toDateString();
        $this->frequency_type = $subscription->frequency_type;
        $this->custom_interval_days = (string) ($subscription->custom_frequency_config['interval_days'] ?? '30');
        $this->status = $subscription->status;
        $this->packageLines = $subscription->subscriptionPackages->map(fn ($sp) => [
            'package_id' => (string) $sp->package_id,
            'quantity' => (string) $sp->quantity,
        ])->all();

        if (empty($this->packageLines)) {
            $this->packageLines = [['package_id' => '', 'quantity' => '1']];
        }

        $this->showDrawer = true;
    }

    public function addPackageLine(): void
    {
        $this->packageLines[] = ['package_id' => '', 'quantity' => '1'];
    }

    public function removePackageLine(int $index): void
    {
        unset($this->packageLines[$index]);
        $this->packageLines = array_values($this->packageLines);
    }

    public function save(): void
    {
        $data = $this->validate();

        $frequencyConfig = $this->frequency_type === 'custom'
            ? ['interval_days' => (int) $this->custom_interval_days]
            : null;

        $attributes = [
            'customer_id' => $data['customer_id'],
            'start_date' => $data['start_date'],
            'frequency_type' => $data['frequency_type'],
            'custom_frequency_config' => $frequencyConfig,
            'status' => $data['status'],
        ];

        if ($this->editing) {
            $this->editing->update($attributes);
            $subscription = $this->editing;
        } else {
            $attributes['created_by'] = Auth::id();
            $attributes['next_collection_date'] = $data['start_date'];
            $subscription = Subscription::create($attributes);
        }

        $subscription->subscriptionPackages()->delete();
        foreach ($data['packageLines'] as $line) {
            $subscription->subscriptionPackages()->create([
                'package_id' => $line['package_id'],
                'quantity' => $line['quantity'],
            ]);
        }

        $this->dispatch('notify', type: 'success', message: $this->editing ? 'Subscription updated.' : 'Subscription created.');
        $this->closeDrawer();
    }

    public function togglePause(Subscription $subscription): void
    {
        $subscription->update(['status' => $subscription->status === 'active' ? 'paused' : 'active']);
        $this->dispatch('notify', type: 'success', message: "Subscription {$subscription->status}.");
    }

    public function closeDrawer(): void
    {
        $this->showDrawer = false;
        $this->reset(['editing', 'customer_id', 'packageLines']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.subscriptions.index', [
            'subscriptions' => Subscription::query()
                ->with('customer', 'subscriptionPackages.package')
                ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
                ->when($this->search, fn ($q) => $q->whereHas('customer', fn ($c) => $c->where('name', 'like', "%{$this->search}%")))
                ->latest()
                ->paginate(15),
        ])->title('Subscriptions');
    }
}
