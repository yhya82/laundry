<?php

namespace App\Services;

use App\Models\CustomerAddress;
use App\Models\Delivery;
use App\Models\DeliveryStatusHistory;
use App\Models\Employee;
use App\Models\LaundryOrder;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Owns delivery creation and the status lifecycle (pending -> scheduled ->
 * assigned -> picked_up -> out_for_delivery -> delivered, with failed ->
 * reschedule and cancel branches). Unlike orders' single-track stage
 * sequence, trg_deliveries_check_order_type is the DB's only guard here —
 * it fires once, on INSERT, and says nothing about the status column
 * itself (chk_deliveries_status only constrains the allowed set of
 * strings) — so the transition graph below is entirely app-enforced.
 */
class DeliveryService
{
    public function __construct(private NotificationService $notificationService) {}

    public function createDelivery(LaundryOrder $order, array $data, User $actor): Delivery
    {
        if ($order->delivery_type !== 'delivery') {
            // trg_deliveries_check_order_type is the real authority — this
            // pre-check keeps the failure readable instead of a raw
            // SQLSTATE, and matches the exit criteria's "handled
            // gracefully in the UI" requirement.
            throw ValidationException::withMessages([
                'delivery_type' => 'This order is not marked for delivery — only pickup is available.',
            ]);
        }

        if ($order->delivery) {
            throw ValidationException::withMessages([
                'delivery_type' => 'This order already has a delivery scheduled.',
            ]);
        }

        return DB::transaction(function () use ($order, $data, $actor) {
            $addressSnapshot = $data['address_snapshot'];

            if (! empty($data['address_id'])) {
                $address = CustomerAddress::findOrFail($data['address_id']);
                $addressSnapshot = $this->formatAddress($address);
            }

            $fee = $data['delivery_fee'] ?? '0';

            $delivery = Delivery::create([
                'laundry_order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'delivery_type' => 'delivery',
                'address_id' => $data['address_id'] ?: null,
                'address_snapshot' => $addressSnapshot,
                'delivery_fee' => $fee,
                'status' => 'pending',
                'scheduled_date' => $data['scheduled_date'] ?: null,
                'delivery_instructions' => $data['delivery_instructions'] ?: null,
            ]);

            DeliveryStatusHistory::create([
                'delivery_id' => $delivery->id,
                'status' => 'pending',
                'notes' => 'Delivery scheduled.',
                'changed_by' => $actor->id,
            ]);

            $this->applyFeeToOrder($order, $fee);

            return $delivery->fresh();
        });
    }

    /**
     * Keeps laundry_orders.delivery_fee_amount / total_amount in sync with
     * the delivery's own fee — chk_laundry_orders_totals requires
     * total_amount = subtotal - discount + delivery_fee at all times, so
     * both columns move together in the same bcmath step. Same
     * incremental-maintenance approach as PaymentService's
     * outstanding_balance note: this composes with the existing
     * remainingBalance computation on the order page without any special
     * casing, even if the order was already fully paid before the fee
     * was added — it simply increases what's still owed.
     */
    private function applyFeeToOrder(LaundryOrder $order, string $fee): void
    {
        if (bccomp($fee, '0', 2) === 0) {
            return;
        }

        $newTotal = bcadd((string) $order->total_amount, $fee, 2);
        $order->update([
            'delivery_fee_amount' => bcadd((string) $order->delivery_fee_amount, $fee, 2),
            'total_amount' => $newTotal,
        ]);

        $order->customer()->increment('outstanding_balance', $fee);
    }

    public function schedule(Delivery $delivery, string $date, User $actor): Delivery
    {
        $this->assertStatusIn($delivery, ['pending', 'scheduled'], 'scheduled');

        return $this->transition($delivery, 'scheduled', $actor, ['scheduled_date' => $date], "Scheduled for {$date}.");
    }

    public function assignStaff(Delivery $delivery, int $employeeId, User $actor): Delivery
    {
        $this->assertStatusIn($delivery, ['pending', 'scheduled', 'assigned'], 'assigned');

        return $this->transition($delivery, 'assigned', $actor, ['assigned_staff_id' => $employeeId], 'Staff assigned.');
    }

    public function markPickedUp(Delivery $delivery, User $actor): Delivery
    {
        $this->assertStatusIn($delivery, ['assigned'], 'marked picked up');

        return $this->transition($delivery, 'picked_up', $actor, [], 'Picked up from the store.');
    }

    public function markOutForDelivery(Delivery $delivery, User $actor): Delivery
    {
        $this->assertStatusIn($delivery, ['picked_up'], 'marked out for delivery');

        return $this->transition($delivery, 'out_for_delivery', $actor, [], 'Out for delivery.');
    }

    public function markDelivered(Delivery $delivery, User $actor): Delivery
    {
        $this->assertStatusIn($delivery, ['out_for_delivery'], 'marked delivered');

        return $this->transition($delivery, 'delivered', $actor, ['completed_date' => now()->toDateString()], 'Delivered successfully.');
    }

    public function markFailed(Delivery $delivery, string $reason, User $actor): Delivery
    {
        $this->assertStatusIn($delivery, ['assigned', 'picked_up', 'out_for_delivery'], 'marked failed');

        return $this->transition($delivery, 'failed', $actor, ['failure_reason' => $reason], "Delivery attempt failed: {$reason}");
    }

    public function reschedule(Delivery $delivery, string $newDate, User $actor): Delivery
    {
        $this->assertStatusIn($delivery, ['failed'], 'rescheduled');

        return $this->transition($delivery, 'scheduled', $actor, [
            'scheduled_date' => $newDate,
            'failure_reason' => null,
        ], "Rescheduled for {$newDate}.");
    }

    public function cancel(Delivery $delivery, string $reason, User $actor): Delivery
    {
        $this->assertStatusIn($delivery, ['pending', 'scheduled', 'assigned', 'picked_up', 'out_for_delivery', 'failed'], 'cancelled');

        return $this->transition($delivery, 'cancelled', $actor, [], "Cancelled: {$reason}");
    }

    private function transition(Delivery $delivery, string $status, User $actor, array $extra, string $note): Delivery
    {
        return DB::transaction(function () use ($delivery, $status, $actor, $extra, $note) {
            $delivery->update(array_merge($extra, ['status' => $status]));

            DeliveryStatusHistory::create([
                'delivery_id' => $delivery->id,
                'status' => $status,
                'notes' => $note,
                'changed_by' => $actor->id,
            ]);

            $fresh = $delivery->fresh();

            $this->notifyStatusChange($fresh, $status);

            return $fresh;
        });
    }

    private function notifyStatusChange(Delivery $delivery, string $status): void
    {
        if ($status === 'assigned') {
            $userId = Employee::find($delivery->assigned_staff_id)?->user_id;

            if ($userId) {
                $this->notificationService->notifyUser(
                    $userId,
                    NotificationService::TYPE_DELIVERY_ASSIGNED,
                    'Delivery assigned to you',
                    "You've been assigned a delivery for order {$delivery->laundryOrder->order_number}.",
                    ['delivery_id' => $delivery->id],
                );
            }

            return;
        }

        $customerNotice = match ($status) {
            'out_for_delivery' => [NotificationService::TYPE_DELIVERY_OUT_FOR_DELIVERY, 'Your order is out for delivery', "Order {$delivery->laundryOrder->order_number} is on its way to you."],
            'delivered' => [NotificationService::TYPE_DELIVERY_DELIVERED, 'Your order was delivered', "Order {$delivery->laundryOrder->order_number} has been delivered."],
            'failed' => [NotificationService::TYPE_DELIVERY_FAILED, 'Delivery attempt failed', "We couldn't deliver order {$delivery->laundryOrder->order_number}: {$delivery->failure_reason}"],
            default => null,
        };

        if ($customerNotice) {
            [$type, $title, $message] = $customerNotice;
            $this->notificationService->notifyCustomer($delivery->customer_id, $type, $title, $message, ['delivery_id' => $delivery->id]);
        }
    }

    /**
     * trg_deliveries_check_order_type carries no BR-xxx number in its
     * message (unlike most other triggers), so this matches on a literal
     * substring of the SIGNAL text — same idiom Customers/Show.php already
     * uses for the other BR-number-less trigger in this schema.
     */
    public function translateOrderTypeError(QueryException $e): ?string
    {
        if ($e->getCode() === '45000' && str_contains($e->getMessage(), 'cannot be created for an order not marked')) {
            return 'This order is not marked for delivery — only pickup is available.';
        }

        return null;
    }

    private function formatAddress(CustomerAddress $address): string
    {
        return collect([$address->street, $address->area, $address->city])
            ->filter()
            ->implode(', ');
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function assertStatusIn(Delivery $delivery, array $allowed, string $verb): void
    {
        if (! in_array($delivery->status, $allowed, true)) {
            throw new RuntimeException("This delivery cannot be {$verb} from its current status (\"{$delivery->status}\").");
        }
    }
}
