<?php

namespace App\Livewire\Orders;

use App\Models\Employee;
use App\Models\LaundryOrder;
use App\Models\Machine;
use App\Models\Payment;
use App\Services\LaundryOrderService;
use App\Services\PaymentService;
use App\Services\StoreCreditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Show extends Component
{
    public LaundryOrder $order;

    public bool $showCancelDrawer = false;

    public string $cancelReason = '';

    public ?int $assignEmployeeId = null;

    public ?int $assignMachineId = null;

    public bool $showPaymentDrawer = false;

    public string $paymentAmount = '';

    public string $paymentMethod = 'cash';

    public string $paymentReference = '';

    public bool $showRefundDrawer = false;

    public ?int $refundingPaymentId = null;

    public string $refundAmount = '';

    public string $refundReason = '';

    public bool $refundAsStoreCredit = false;

    public bool $showApplyCreditDrawer = false;

    public string $applyCreditAmount = '';

    public bool $showCancelReceiptDrawer = false;

    public string $cancelReceiptReason = '';

    public function mount(LaundryOrder $order): void
    {
        $this->order = $order->load(['customer', 'packages.package', 'packages.items.clothingType', 'stageHistory', 'discounts', 'payments.refunds', 'receipt', 'assignedEmployee', 'machine']);
        $this->assignEmployeeId = $order->assigned_employee_id;
        $this->assignMachineId = $order->machine_id;
    }

    #[Computed]
    public function remainingBalance(): string
    {
        $paid = $this->order->payments()->whereIn('payment_status', ['paid', 'partial'])->sum('amount');

        return bcsub((string) $this->order->total_amount, (string) $paid, 2);
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

    private function refreshOrder(): void
    {
        $this->order = $this->order->fresh(['customer', 'payments.refunds', 'receipt']);
    }

    public function openPaymentDrawer(): void
    {
        $this->paymentAmount = $this->remainingBalance;
        $this->paymentMethod = 'cash';
        $this->paymentReference = '';
        $this->showPaymentDrawer = true;
    }

    public function recordPayment(PaymentService $service): void
    {
        $this->validate([
            'paymentAmount' => ['required', 'numeric', 'gt:0'],
            'paymentMethod' => ['required', 'in:cash,card,mobile_money,bank_transfer,credit'],
            'paymentReference' => ['nullable', 'string', 'max:150'],
        ]);

        try {
            $service->recordPayment($this->order, [
                'amount' => $this->paymentAmount,
                'payment_method' => $this->paymentMethod,
                'reference' => $this->paymentReference ?: null,
            ], Auth::user());
        } catch (ValidationException $e) {
            $this->addError('paymentAmount', $e->validator->errors()->first('amount'));

            return;
        }

        $this->refreshOrder();
        $this->showPaymentDrawer = false;
        $this->dispatch('notify', type: 'success', message: 'Payment recorded.');
    }

    #[Computed]
    public function refundingPayment(): ?Payment
    {
        return $this->refundingPaymentId
            ? $this->order->payments->firstWhere('id', $this->refundingPaymentId)
            : null;
    }

    #[Computed]
    public function refundMax(): string
    {
        $payment = $this->refundingPayment;

        if (! $payment) {
            return '0.00';
        }

        return bcsub((string) $payment->amount, (string) $payment->refunds->sum('amount'), 2);
    }

    public function openRefundDrawer(int $paymentId): void
    {
        $this->refundingPaymentId = $paymentId;
        $this->refundAmount = $this->refundMax;
        $this->refundReason = '';
        $this->refundAsStoreCredit = false;
        $this->showRefundDrawer = true;
    }

    public function refundPayment(PaymentService $service): void
    {
        $this->validate([
            'refundAmount' => ['required', 'numeric', 'gt:0'],
            'refundReason' => ['required', 'string', 'max:255'],
        ]);

        try {
            $service->refundPayment(
                $this->refundingPayment,
                $this->refundAmount,
                $this->refundReason,
                Auth::user(),
                $this->refundAsStoreCredit,
            );
        } catch (ValidationException $e) {
            $this->addError('refundAmount', $e->validator->errors()->first('amount'));

            return;
        }

        $this->refreshOrder();
        $this->showRefundDrawer = false;
        $this->dispatch('notify', type: 'success', message: 'Refund processed.');
    }

    public function openApplyCreditDrawer(): void
    {
        $available = (string) $this->order->customer->store_credit_balance;
        $this->applyCreditAmount = bccomp($available, $this->remainingBalance, 2) > 0 ? $this->remainingBalance : $available;
        $this->showApplyCreditDrawer = true;
    }

    public function applyStoreCredit(PaymentService $service, StoreCreditService $storeCreditService): void
    {
        $this->validate([
            'applyCreditAmount' => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            $service->applyStoreCredit($this->order, $this->applyCreditAmount, Auth::user(), $storeCreditService);
        } catch (ValidationException $e) {
            $this->addError('applyCreditAmount', $e->validator->errors()->first('amount'));

            return;
        }

        $this->refreshOrder();
        $this->showApplyCreditDrawer = false;
        $this->dispatch('notify', type: 'success', message: 'Store credit applied.');
    }

    public function cancelReceipt(PaymentService $service): void
    {
        $this->validate(['cancelReceiptReason' => ['required', 'string', 'max:255']]);

        $service->cancelReceipt($this->order->receipt, $this->cancelReceiptReason);

        $this->refreshOrder();
        $this->showCancelReceiptDrawer = false;
        $this->dispatch('notify', type: 'warning', message: 'Receipt cancelled.');
    }

    public function render()
    {
        return view('livewire.orders.show')->title($this->order->order_number);
    }
}
