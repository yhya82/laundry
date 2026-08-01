<?php

namespace App\Livewire\Orders;

use App\Models\ClothingType;
use App\Models\Customer;
use App\Models\DiscountTemplate;
use App\Models\Package;
use App\Services\LaundryOrderService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Terminal extends Component
{
    // --- Customer ---
    public string $customerSearch = '';

    public ?int $selectedCustomerId = null;

    public bool $showNewCustomerForm = false;

    public string $newCustomerName = '';

    public string $newCustomerPhone = '';

    // --- Cart ---
    /** @var array<int, array{package_id: int, package_name: string, unit_price: string, maximum_clothes: int, items: array<int, array{clothing_type_id: int, clothing_type_name: string, quantity: int}>}> */
    public array $cart = [];

    public ?int $activeCartLine = null;

    public string $instructions = '';

    // --- Discount ---
    public ?int $discountTemplateId = null;

    public bool $useCustomDiscount = false;

    public string $customDiscountType = 'percentage';

    public string $customDiscountValue = '0';

    public string $discountReason = '';

    // --- Payment ---
    public string $paymentMethod = 'cash';

    public string $paymentAmount = '0';

    public ?string $paymentReference = null;

    public bool $payNow = true;

    public array $orderErrors = [];

    #[Computed]
    public function customerResults()
    {
        if ($this->selectedCustomerId || strlen($this->customerSearch) < 2) {
            return collect();
        }

        return Customer::where('status', 'active')
            ->where(fn ($q) => $q->where('name', 'like', "%{$this->customerSearch}%")->orWhere('phone', 'like', "{$this->customerSearch}%"))
            ->limit(8)
            ->get();
    }

    #[Computed]
    public function selectedCustomer(): ?Customer
    {
        return $this->selectedCustomerId ? Customer::find($this->selectedCustomerId) : null;
    }

    #[Computed]
    public function activePackages()
    {
        return Package::where('status', 'active')->orderBy('name')->get();
    }

    #[Computed]
    public function activeClothingTypes()
    {
        return ClothingType::where('status', 'active')->orderBy('name')->get();
    }

    #[Computed]
    public function activeDiscountTemplates()
    {
        return DiscountTemplate::where('status', 'active')->orderBy('name')->get();
    }

    #[Computed]
    public function subtotal(): string
    {
        return (string) array_reduce(
            $this->cart,
            fn ($carry, $line) => bcadd($carry, $line['unit_price'], 2),
            '0'
        );
    }

    #[Computed]
    public function discountPreview(): string
    {
        $subtotal = $this->subtotal;

        if ($this->discountTemplateId) {
            $template = DiscountTemplate::find($this->discountTemplateId);
            if (! $template) {
                return '0';
            }

            return $template->discount_type === 'percentage'
                ? bcdiv(bcmul($subtotal, (string) $template->value, 4), '100', 2)
                : (string) min((float) $template->value, (float) $subtotal);
        }

        if ($this->useCustomDiscount) {
            return $this->customDiscountType === 'percentage'
                ? bcdiv(bcmul($subtotal, $this->customDiscountValue ?: '0', 4), '100', 2)
                : (string) min((float) ($this->customDiscountValue ?: 0), (float) $subtotal);
        }

        return '0';
    }

    #[Computed]
    public function total(): string
    {
        return bcsub($this->subtotal, $this->discountPreview, 2);
    }

    public function selectCustomer(int $customerId): void
    {
        $this->selectedCustomerId = $customerId;
        $this->customerSearch = '';
    }

    public function clearCustomer(): void
    {
        $this->selectedCustomerId = null;
    }

    public function createQuickCustomer(): void
    {
        $this->validate([
            'newCustomerName' => ['required', 'string', 'max:150'],
            'newCustomerPhone' => [
                'required', 'string', 'max:30',
                function ($attribute, $value, $fail) {
                    if (Customer::where('phone', $value)->whereNull('deleted_at')->exists()) {
                        $fail('An active customer with this phone number already exists.');
                    }
                },
            ],
        ]);

        $customer = Customer::create([
            'name' => $this->newCustomerName,
            'phone' => $this->newCustomerPhone,
            'customer_type' => 'walk_in',
            'status' => 'active',
        ]);

        $this->selectedCustomerId = $customer->id;
        $this->showNewCustomerForm = false;
        $this->reset(['newCustomerName', 'newCustomerPhone']);
    }

    public function addPackage(int $packageId): void
    {
        $package = Package::findOrFail($packageId);

        $this->cart[] = [
            'package_id' => $package->id,
            'package_name' => $package->name,
            'unit_price' => (string) $package->price,
            'maximum_clothes' => $package->maximum_clothes,
            'items' => [],
        ];

        $this->activeCartLine = array_key_last($this->cart);
    }

    public function removePackageLine(int $index): void
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
        $this->activeCartLine = null;
    }

    public function setActiveCartLine(int $index): void
    {
        $this->activeCartLine = $index;
    }

    public function cartLineItemCount(int $index): int
    {
        return array_sum(array_column($this->cart[$index]['items'] ?? [], 'quantity'));
    }

    public function addClothingItem(int $clothingTypeId): void
    {
        if ($this->activeCartLine === null || ! isset($this->cart[$this->activeCartLine])) {
            return;
        }

        $line = &$this->cart[$this->activeCartLine];

        if ($this->cartLineItemCount($this->activeCartLine) >= $line['maximum_clothes']) {
            $this->addError('cart', "\"{$line['package_name']}\" allows at most {$line['maximum_clothes']} clothing items.");

            return;
        }

        $clothingType = ClothingType::findOrFail($clothingTypeId);

        foreach ($line['items'] as &$existing) {
            if ($existing['clothing_type_id'] === $clothingTypeId) {
                $existing['quantity']++;

                return;
            }
        }

        $line['items'][] = [
            'clothing_type_id' => $clothingType->id,
            'clothing_type_name' => $clothingType->name,
            'quantity' => 1,
        ];
    }

    public function decrementClothingItem(int $lineIndex, int $itemIndex): void
    {
        if (! isset($this->cart[$lineIndex]['items'][$itemIndex])) {
            return;
        }

        $this->cart[$lineIndex]['items'][$itemIndex]['quantity']--;

        if ($this->cart[$lineIndex]['items'][$itemIndex]['quantity'] <= 0) {
            unset($this->cart[$lineIndex]['items'][$itemIndex]);
            $this->cart[$lineIndex]['items'] = array_values($this->cart[$lineIndex]['items']);
        }
    }

    public function completeOrder(LaundryOrderService $service)
    {
        $this->orderErrors = [];
        $this->resetValidation();

        if (! $this->selectedCustomerId) {
            $this->addError('customer', 'Select or create a customer before completing the order.');

            return;
        }

        if (empty($this->cart)) {
            $this->addError('cart', 'Add at least one package before completing the order.');

            return;
        }

        $discount = null;

        if ($this->discountTemplateId) {
            $discount = ['discount_template_id' => $this->discountTemplateId, 'discount_type' => 'percentage', 'value' => '0', 'reason' => ''];
        } elseif ($this->useCustomDiscount && (float) $this->customDiscountValue > 0) {
            if (trim($this->discountReason) === '') {
                $this->addError('discountReason', 'A reason is required for a manual discount.');

                return;
            }

            $discount = [
                'discount_template_id' => null,
                'discount_type' => $this->customDiscountType,
                'value' => $this->customDiscountValue,
                'reason' => $this->discountReason,
            ];
        }

        $payment = null;

        if ($this->payNow && (float) $this->paymentAmount > 0) {
            $payment = [
                'amount' => $this->paymentAmount,
                'payment_method' => $this->paymentMethod,
                'reference' => $this->paymentReference,
            ];
        }

        try {
            $order = $service->createWalkInOrder([
                'customer_id' => $this->selectedCustomerId,
                'instructions' => $this->instructions,
                'cart' => array_map(fn ($line) => [
                    'package_id' => $line['package_id'],
                    'items' => array_map(fn ($item) => [
                        'clothing_type_id' => $item['clothing_type_id'],
                        'quantity' => $item['quantity'],
                    ], $line['items']),
                ], $this->cart),
                'discount' => $discount,
                'payment' => $payment,
            ], Auth::user());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        }

        $this->dispatch('notify', type: 'success', message: "Order {$order->order_number} created.");

        return $this->redirect(route('orders.show', $order), navigate: false);
    }

    public function render()
    {
        return view('livewire.orders.terminal')->title('Laundry Terminal');
    }
}
