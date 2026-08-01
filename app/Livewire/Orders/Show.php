<?php

namespace App\Livewire\Orders;

use App\Models\DamageType;
use App\Models\Employee;
use App\Models\LaundryOrder;
use App\Models\Machine;
use App\Models\Payment;
use App\Services\DamageService;
use App\Services\LaundryOrderService;
use App\Services\PaymentService;
use App\Services\StoreCreditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Show extends Component
{
    use WithFileUploads;

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

    public bool $showDamageDrawer = false;

    public string $damageDescription = '';

    /** @var array<int, array{laundry_order_item_id: int, item_label: string, damage_type_id: ?int, severity: string, description: string}> */
    public array $damageItems = [];

    public ?int $pendingItemId = null;

    /** @var array<int, TemporaryUploadedFile> */
    public array $damageEvidence = [];

    public function mount(LaundryOrder $order): void
    {
        $this->order = $order->load(['customer', 'packages.package', 'packages.items.clothingType', 'stageHistory', 'discounts', 'payments.refunds', 'receipt', 'assignedEmployee', 'machine', 'damageReports']);
        $this->assignEmployeeId = $order->assigned_employee_id;
        $this->assignMachineId = $order->machine_id;
    }

    /** @return array<int, array{id: int, label: string}> */
    #[Computed]
    public function orderItemOptions(): array
    {
        $options = [];

        foreach ($this->order->packages as $package) {
            foreach ($package->items as $item) {
                $options[] = [
                    'id' => $item->id,
                    'label' => "{$package->package->name} — {$item->clothingType->name} (x{$item->quantity})",
                ];
            }
        }

        return $options;
    }

    #[Computed]
    public function damageTypes()
    {
        return DamageType::where('status', 'active')->orderBy('name')->get();
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

    public function openDamageDrawer(): void
    {
        $this->damageDescription = '';
        $this->damageItems = [];
        $this->pendingItemId = null;
        $this->damageEvidence = [];
        $this->showDamageDrawer = true;
    }

    public function addDamageItem(): void
    {
        if (! $this->pendingItemId) {
            return;
        }

        foreach ($this->damageItems as $existing) {
            if ($existing['laundry_order_item_id'] === $this->pendingItemId) {
                return;
            }
        }

        $option = collect($this->orderItemOptions)->firstWhere('id', $this->pendingItemId);

        if (! $option) {
            return;
        }

        $this->damageItems[] = [
            'laundry_order_item_id' => $option['id'],
            'item_label' => $option['label'],
            'damage_type_id' => null,
            'severity' => 'medium',
            'description' => '',
        ];

        $this->pendingItemId = null;
    }

    public function removeDamageItem(int $index): void
    {
        unset($this->damageItems[$index]);
        $this->damageItems = array_values($this->damageItems);
    }

    public function reportDamage(DamageService $service): void
    {
        $this->validate([
            'damageDescription' => ['required', 'string', 'max:1000'],
            'damageItems' => ['required', 'array', 'min:1'],
            'damageItems.*.damage_type_id' => ['required', 'integer', 'exists:damage_types,id'],
            'damageItems.*.severity' => ['required', 'in:low,medium,high,critical'],
            'damageEvidence.*' => ['nullable', 'image', 'max:5120'],
        ], [], [
            'damageItems' => 'items',
            'damageItems.*.damage_type_id' => 'damage type',
            'damageItems.*.severity' => 'severity',
        ]);

        try {
            $report = $service->createReport($this->order, $this->damageDescription, array_map(fn ($item) => [
                'laundry_order_item_id' => $item['laundry_order_item_id'],
                'damage_type_id' => $item['damage_type_id'],
                'severity' => $item['severity'],
                'description' => $item['description'] ?: null,
            ], $this->damageItems), Auth::user());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        }

        foreach ($this->damageEvidence as $file) {
            $service->addEvidence($report, $file, Auth::user());
        }

        $this->order = $this->order->fresh(['damageReports']);
        $this->showDamageDrawer = false;
        $this->dispatch('notify', type: 'success', message: 'Damage report filed.');
    }

    public function render()
    {
        return view('livewire.orders.show')->title($this->order->order_number);
    }
}
