<?php

namespace App\Livewire\Orders;

use App\Models\Employee;
use App\Models\LaundryOrder;
use App\Models\Machine;
use App\Services\LaundryOrderService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Show extends Component
{
    public LaundryOrder $order;

    public bool $showCancelDrawer = false;

    public string $cancelReason = '';

    public ?int $assignEmployeeId = null;

    public ?int $assignMachineId = null;

    public function mount(LaundryOrder $order): void
    {
        $this->order = $order->load(['customer', 'packages.package', 'packages.items.clothingType', 'stageHistory', 'discounts', 'payments', 'receipt', 'assignedEmployee', 'machine']);
        $this->assignEmployeeId = $order->assigned_employee_id;
        $this->assignMachineId = $order->machine_id;
    }

    #[Computed]
    public function nextStage(): ?string
    {
        $index = array_search($this->order->status, LaundryOrderService::STAGES, true);

        if ($index === false || $index === array_key_last(LaundryOrderService::STAGES)) {
            return null;
        }

        return LaundryOrderService::STAGES[$index + 1];
    }

    #[Computed]
    public function capacityWarning(): ?string
    {
        if (! $this->nextStage) {
            return null;
        }

        $service = app(LaundryOrderService::class);
        $occupancy = $service->stageOccupancy($this->nextStage);
        $max = $service->maxProcessingCapacity();

        if ($occupancy >= $max) {
            return "The \"{$this->nextStage}\" stage is at or above its configured capacity ({$occupancy}/{$max}). You can still proceed — this is a heads-up, not a block.";
        }

        return null;
    }

    #[Computed]
    public function employees()
    {
        return Employee::where('status', 'active')->orderBy('name')->get();
    }

    #[Computed]
    public function machines()
    {
        return Machine::orderBy('name')->get();
    }

    public function advanceStage(LaundryOrderService $service): void
    {
        $this->order = $service->advanceStage($this->order, $this->assignMachineId, Auth::user())
            ->load(['stageHistory', 'assignedEmployee', 'machine']);

        $this->dispatch('notify', type: 'success', message: "Order advanced to \"{$this->order->status}\".");
    }

    public function saveAssignment(): void
    {
        $this->order->update([
            'assigned_employee_id' => $this->assignEmployeeId,
            'machine_id' => $this->assignMachineId,
        ]);

        $this->dispatch('notify', type: 'success', message: 'Assignment updated.');
    }

    public function cancelOrder(LaundryOrderService $service): void
    {
        $this->validate(['cancelReason' => ['required', 'string', 'max:255']]);

        $this->order = $service->cancelOrder($this->order, $this->cancelReason, Auth::user());
        $this->showCancelDrawer = false;
        $this->dispatch('notify', type: 'warning', message: 'Order cancelled.');
    }

    public function render()
    {
        return view('livewire.orders.show')->title($this->order->order_number);
    }
}
