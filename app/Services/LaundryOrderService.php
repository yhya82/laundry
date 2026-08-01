<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\DiscountTemplate;
use App\Models\LaundryOrder;
use App\Models\LaundryOrderStageHistory;
use App\Models\Package;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Owns the T1 transaction boundary (customer → order → packages → items →
 * payment → receipt) and the stage-advance bookkeeping — kept out of
 * Livewire components per IMPLEMENTATION_PLAN.md Phase 1's own convention
 * ("app/Services/<Module>Service.php for anything with a DB transaction
 * boundary... so trigger-adjacent logic isn't scattered across components").
 * Payment/receipt recording itself lives in PaymentService (Phase 8) —
 * this class delegates to it rather than duplicating that logic.
 */
class LaundryOrderService
{
    /** Stage order mirrors trg_laundry_orders_stage_sequence's FIELD() list exactly. */
    public const STAGES = [
        'waiting_queue', 'received', 'sorting', 'washing', 'drying',
        'ironing', 'quality_check', 'packaging', 'ready', 'completed',
    ];

    public function __construct(private PaymentService $paymentService, private NotificationService $notificationService) {}

    /**
     * @param  array{customer_id: int, delivery_type?: string, instructions?: ?string, cart: array<int, array{package_id: int, items: array<int, array{clothing_type_id: int, quantity: int}>}>, discount?: ?array{discount_template_id: ?int, discount_type: string, value: string, reason: string}, payment?: ?array{amount: string, payment_method: string, reference: ?string}}  $data
     */
    public function createWalkInOrder(array $data, User $actor): LaundryOrder
    {
        if (empty($data['cart'])) {
            throw ValidationException::withMessages(['cart' => 'At least one package must be added before completing the order.']);
        }

        return DB::transaction(function () use ($data, $actor) {
            $customer = Customer::findOrFail($data['customer_id']);

            $order = LaundryOrder::create([
                'order_number' => $this->generateOrderNumber(),
                'customer_id' => $customer->id,
                'status' => 'waiting_queue',
                'delivery_type' => $data['delivery_type'] ?? 'pickup',
                'instructions' => ($data['instructions'] ?? null) ?: 'No special instructions',
                'created_by' => $actor->id,
                // Explicit, not left to the columns' DB-level DEFAULT 0.00 —
                // Eloquent's create() doesn't reflect a DB default back into
                // the in-memory model, so $order->delivery_fee_amount would
                // read as null (not 0.00) for the rest of this transaction,
                // caught by generateReceipt() failing a NOT NULL constraint.
                'subtotal_amount' => 0,
                'discount_amount' => 0,
                'delivery_fee_amount' => 0,
                'total_amount' => 0,
                'store_credit_applied_amount' => 0,
            ]);

            LaundryOrderStageHistory::create([
                'laundry_order_id' => $order->id,
                'stage' => 'waiting_queue',
                'started_at' => now(),
                'changed_by' => $actor->id,
            ]);

            $subtotal = '0';
            $hasExpress = false;

            foreach ($data['cart'] as $line) {
                $package = Package::findOrFail($line['package_id']);
                $quantity = 1;
                $lineTotal = bcmul((string) $package->price, (string) $quantity, 2);
                $subtotal = bcadd($subtotal, $lineTotal, 2);
                $hasExpress = $hasExpress || $package->priority === 'express';

                $orderPackage = $order->packages()->create([
                    'package_id' => $package->id,
                    'quantity' => $quantity,
                    'unit_price_snapshot' => $package->price,
                    'line_total' => $lineTotal,
                ]);

                // trg_loi_check_package_limit_ins is the actual authority on
                // maximum_clothes — this running-total pre-check exists only
                // so the cashier gets a clear error instead of a
                // mid-transaction SQLSTATE 45000 surfacing from this loop.
                $itemQtyTotal = 0;

                foreach ($line['items'] as $item) {
                    if ($item['quantity'] < 1) {
                        continue;
                    }

                    $itemQtyTotal += $item['quantity'];

                    if ($itemQtyTotal > $package->maximum_clothes) {
                        throw ValidationException::withMessages([
                            'cart' => "\"{$package->name}\" allows at most {$package->maximum_clothes} clothing items per package instance.",
                        ]);
                    }

                    $orderPackage->items()->create([
                        'clothing_type_id' => $item['clothing_type_id'],
                        'quantity' => $item['quantity'],
                    ]);
                }
            }

            $discountAmount = '0';

            if (! empty($data['discount'])) {
                $discountAmount = $this->applyDiscount($order, $data['discount'], $subtotal, $actor);
            }

            $total = bcsub($subtotal, $discountAmount, 2);

            $order->update([
                'subtotal_amount' => $subtotal,
                'discount_amount' => $discountAmount,
                'total_amount' => $total,
                'priority' => $hasExpress ? 'express' : 'normal',
            ]);

            // outstanding_balance is application-maintained (see
            // PaymentService's own note) — this is the debit half: the
            // order's finalized total is what the customer now owes,
            // before any payment recorded against it below reduces it.
            $customer->increment('outstanding_balance', $total);

            if (! empty($data['payment']) && (float) $data['payment']['amount'] > 0) {
                $this->paymentService->recordPayment($order, $data['payment'], $actor);
            }

            return $order->fresh(['packages.items', 'payments', 'receipt', 'discounts']);
        });
    }

    /**
     * @param  array{discount_template_id: ?int, discount_type: string, value: string, reason: string}  $discount
     */
    private function applyDiscount(LaundryOrder $order, array $discount, string $subtotal, User $actor): string
    {
        $template = ! empty($discount['discount_template_id'])
            ? DiscountTemplate::find($discount['discount_template_id'])
            : null;

        $type = $template->discount_type ?? $discount['discount_type'];
        $value = (string) ($template->value ?? $discount['value']);

        $amount = $type === 'percentage'
            ? bcdiv(bcmul($subtotal, $value, 4), '100', 2)
            : min((float) $value, (float) $subtotal);
        $amount = (string) $amount;

        $needsApproval = $this->discountNeedsApproval($actor, $template, $type, $value, $subtotal);

        if ($needsApproval && ! $actor->hasPermission('discounts.approve')) {
            throw ValidationException::withMessages([
                'discount' => 'This discount exceeds what a cashier can apply alone — ask a manager to apply it or reduce it.',
            ]);
        }

        $order->discounts()->create([
            'discount_template_id' => $template?->id,
            'discount_type' => $type,
            'value' => $value,
            'amount' => $amount,
            'reason' => $discount['reason'] ?: ($template?->name ?? 'Manual discount'),
            'status' => 'applied',
            'applied_by' => $actor->id,
            'approved_by' => $needsApproval ? $actor->id : null,
        ]);

        return $amount;
    }

    private function discountNeedsApproval(User $actor, ?DiscountTemplate $template, string $type, string $value, string $subtotal): bool
    {
        if ($template) {
            if ($template->requires_approval) {
                return true;
            }

            if ($template->max_cashier_value !== null) {
                $amount = $type === 'percentage'
                    ? bcdiv(bcmul($subtotal, $value, 4), '100', 2)
                    : $value;

                return (float) $amount > (float) $template->max_cashier_value;
            }

            return false;
        }

        // Manual (template-less) discount — governed by the global setting.
        $maxPercent = (float) (Setting::where('setting_group', 'discount')
            ->where('setting_key', 'max_cashier_discount_percent')
            ->value('setting_value') ?? 10);

        $effectivePercent = $type === 'percentage'
            ? (float) $value
            : ((float) $subtotal > 0 ? ((float) $value / (float) $subtotal) * 100 : 0);

        return $effectivePercent > $maxPercent;
    }

    /**
     * Advances an order exactly one stage. trg_laundry_orders_stage_sequence
     * is the real authority (rejects skips/backwards moves); this method
     * only ever offers the single legal next stage, matching §3.10's "the
     * UI enforces this at entry" pattern used elsewhere in the build.
     */
    public function advanceStage(LaundryOrder $order, ?int $machineId, User $actor): LaundryOrder
    {
        $currentIndex = array_search($order->status, self::STAGES, true);

        if ($currentIndex === false || $currentIndex === array_key_last(self::STAGES)) {
            throw new RuntimeException('This order has no further stage to advance to.');
        }

        $nextStage = self::STAGES[$currentIndex + 1];

        return DB::transaction(function () use ($order, $nextStage, $machineId, $actor) {
            $openStage = $order->stageHistory()->whereNull('completed_at')->latest('started_at')->first();
            $openStage?->update(['completed_at' => now()]);

            $order->update(['status' => $nextStage, 'machine_id' => $machineId ?? $order->machine_id]);

            LaundryOrderStageHistory::create([
                'laundry_order_id' => $order->id,
                'stage' => $nextStage,
                'machine_id' => $machineId ?? $order->machine_id,
                'started_at' => now(),
                'changed_by' => $actor->id,
            ]);

            $fresh = $order->fresh();

            if ($nextStage === 'ready') {
                $this->notificationService->notifyCustomer(
                    $fresh->customer_id,
                    NotificationService::TYPE_ORDER_READY,
                    'Your order is ready',
                    "Order {$fresh->order_number} is ready for {$fresh->delivery_type}.",
                    ['laundry_order_id' => $fresh->id],
                );
            }

            return $fresh;
        });
    }

    public function cancelOrder(LaundryOrder $order, string $reason, User $actor): LaundryOrder
    {
        return DB::transaction(function () use ($order, $reason) {
            $openStage = $order->stageHistory()->whereNull('completed_at')->latest('started_at')->first();
            $openStage?->update(['completed_at' => now()]);

            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_reason' => $reason,
            ]);

            $fresh = $order->fresh();

            $this->notificationService->notifyCustomer(
                $fresh->customer_id,
                NotificationService::TYPE_ORDER_CANCELLED,
                'Your order was cancelled',
                "Order {$fresh->order_number} was cancelled: {$reason}",
                ['laundry_order_id' => $fresh->id],
            );

            return $fresh;
        });
    }

    /**
     * How many orders are currently sitting in $stage — the raw material
     * for the BR-029/030/031 "please wait" capacity warning. Deliberately
     * not enforced as a hard block; see triggers.sql's own closing note on
     * why throughput limits stay application-layer, non-blocking.
     */
    public function stageOccupancy(string $stage): int
    {
        return LaundryOrder::where('status', $stage)->count();
    }

    public function maxProcessingCapacity(): int
    {
        return (int) (Setting::where('setting_group', 'laundry')
            ->where('setting_key', 'max_processing_packages')
            ->value('setting_value') ?? 10);
    }

    private function generateOrderNumber(): string
    {
        do {
            $candidate = 'ORD-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
        } while (LaundryOrder::where('order_number', $candidate)->exists());

        return $candidate;
    }
}
