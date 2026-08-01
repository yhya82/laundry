<?php

namespace App\Livewire\Delivery;

use App\Models\Delivery;
use App\Models\Employee;
use App\Services\DeliveryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use RuntimeException;

class Show extends Component
{
    public Delivery $delivery;

    public bool $showScheduleDrawer = false;

    public string $scheduleDate = '';

    public bool $showAssignDrawer = false;

    public ?int $assignEmployeeId = null;

    public bool $showFailDrawer = false;

    public string $failureReason = '';

    public bool $showRescheduleDrawer = false;

    public string $rescheduleDate = '';

    public bool $showCancelDrawer = false;

    public string $cancelReason = '';

    public function mount(Delivery $delivery): void
    {
        $this->delivery = $delivery->load(['laundryOrder', 'customer', 'assignedStaff', 'statusHistory.changedBy']);
    }

    private function refresh(): void
    {
        $this->delivery = $this->delivery->fresh(['laundryOrder', 'customer', 'assignedStaff', 'statusHistory.changedBy']);
    }

    #[Computed]
    public function employees()
    {
        return Employee::where('status', 'active')->orderBy('name')->get();
    }

    private function handle(callable $action, string $successMessage): void
    {
        try {
            $action();
        } catch (RuntimeException|ValidationException $e) {
            $message = $e instanceof ValidationException ? $e->validator->errors()->first() : $e->getMessage();
            $this->dispatch('notify', type: 'error', message: $message);

            return;
        }

        $this->refresh();
        $this->dispatch('notify', type: 'success', message: $successMessage);
    }

    public function schedule(DeliveryService $service): void
    {
        $this->validate(['scheduleDate' => ['required', 'date']]);
        $this->handle(fn () => $service->schedule($this->delivery, $this->scheduleDate, Auth::user()), 'Delivery scheduled.');
        $this->showScheduleDrawer = false;
    }

    public function assignStaff(DeliveryService $service): void
    {
        $this->validate(['assignEmployeeId' => ['required', 'integer', 'exists:employees,id']]);
        $this->handle(fn () => $service->assignStaff($this->delivery, $this->assignEmployeeId, Auth::user()), 'Staff assigned.');
        $this->showAssignDrawer = false;
    }

    public function markPickedUp(DeliveryService $service): void
    {
        $this->handle(fn () => $service->markPickedUp($this->delivery, Auth::user()), 'Marked picked up.');
    }

    public function markOutForDelivery(DeliveryService $service): void
    {
        $this->handle(fn () => $service->markOutForDelivery($this->delivery, Auth::user()), 'Marked out for delivery.');
    }

    public function markDelivered(DeliveryService $service): void
    {
        $this->handle(fn () => $service->markDelivered($this->delivery, Auth::user()), 'Marked delivered.');
    }

    public function markFailed(DeliveryService $service): void
    {
        $this->validate(['failureReason' => ['required', 'string', 'max:255']]);
        $this->handle(fn () => $service->markFailed($this->delivery, $this->failureReason, Auth::user()), 'Delivery marked failed.');
        $this->showFailDrawer = false;
    }

    public function reschedule(DeliveryService $service): void
    {
        $this->validate(['rescheduleDate' => ['required', 'date']]);
        $this->handle(fn () => $service->reschedule($this->delivery, $this->rescheduleDate, Auth::user()), 'Delivery rescheduled.');
        $this->showRescheduleDrawer = false;
    }

    public function cancel(DeliveryService $service): void
    {
        $this->validate(['cancelReason' => ['required', 'string', 'max:255']]);
        $this->handle(fn () => $service->cancel($this->delivery, $this->cancelReason, Auth::user()), 'Delivery cancelled.');
        $this->showCancelDrawer = false;
    }

    public function render()
    {
        return view('livewire.delivery.show')->title("Delivery — {$this->delivery->laundryOrder->order_number}");
    }
}
