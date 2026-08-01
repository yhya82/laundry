<?php

namespace App\Services;

use App\Models\LaundryOrder;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Refund;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Owns payment recording, refunds, and receipt generation — the parts of
 * the walk-in flow LaundryOrderService delegates to rather than
 * duplicating (Phase 6 built the order-creation-time path; this is where
 * it actually lives now, alongside everything Phase 8 adds).
 */
class PaymentService
{
    /**
     * @param  array{amount: string, payment_method: string, reference: ?string}  $payment
     */
    public function recordPayment(LaundryOrder $order, array $payment, User $actor): Payment
    {
        return DB::transaction(function () use ($order, $payment, $actor) {
            $amount = $payment['amount'];
            $alreadyPaid = $order->payments()->whereIn('payment_status', ['paid', 'partial'])->sum('amount');
            $newTotal = bcadd((string) $alreadyPaid, $amount, 2);

            if (bccomp($newTotal, (string) $order->total_amount, 2) > 0) {
                // trg_payments_check_not_exceed_total_ins is the actual
                // authority (BR-034) — this pre-check exists so the error
                // reads as a normal validation message, not a raw SQLSTATE.
                $remaining = bcsub((string) $order->total_amount, (string) $alreadyPaid, 2);
                throw ValidationException::withMessages([
                    'amount' => "This payment would exceed the order total — at most {$remaining} can still be recorded.",
                ]);
            }

            $status = bccomp($newTotal, (string) $order->total_amount, 2) >= 0 ? 'paid' : 'partial';

            $record = $order->payments()->create([
                'payment_number' => $this->generateNumber('PAY', Payment::class, 'payment_number'),
                'customer_id' => $order->customer_id,
                'amount' => $amount,
                'payment_method' => $payment['payment_method'],
                'payment_status' => $status,
                'reference' => $payment['reference'] ?: null,
                'paid_by' => $actor->id,
            ]);

            // outstanding_balance is application-maintained, not
            // trigger-synced (MASTER_SPECIFICATION.md §4.4) — kept current
            // incrementally here; a nightly reconciliation job (§9 Phase 3,
            // not built in this delivery) is the authoritative drift-correction
            // path for anything this misses.
            $order->customer()->decrement('outstanding_balance', $amount);

            if ($status === 'paid') {
                $this->generateReceipt($order, $record, $actor);
            }

            return $record->fresh();
        });
    }

    public function refundPayment(Payment $payment, string $amount, string $reason, User $actor, bool $asStoreCredit = false, ?StoreCreditService $storeCreditService = null): Refund
    {
        return DB::transaction(function () use ($payment, $amount, $reason, $actor, $asStoreCredit, $storeCreditService) {
            $alreadyRefunded = $payment->refunds()->sum('amount');
            $remaining = bcsub((string) $payment->amount, (string) $alreadyRefunded, 2);

            if (bccomp($amount, $remaining, 2) > 0) {
                // trg_refunds_check_total is the actual authority (BR-038).
                throw ValidationException::withMessages([
                    'amount' => "This exceeds the remaining refundable amount ({$remaining}).",
                ]);
            }

            $refund = $payment->refunds()->create([
                'refund_number' => $this->generateNumber('REF', Refund::class, 'refund_number'),
                'amount' => $amount,
                'reason' => $reason,
                'processed_by' => $actor->id,
            ]);

            $newTotalRefunded = bcadd((string) $alreadyRefunded, $amount, 2);
            if (bccomp($newTotalRefunded, (string) $payment->amount, 2) >= 0) {
                $payment->update(['payment_status' => 'refunded']);
            }

            if ($asStoreCredit) {
                ($storeCreditService ?? app(StoreCreditService::class))->credit(
                    $payment->customer,
                    $amount,
                    'refund_conversion',
                    $actor,
                    'refunds',
                    $refund->id,
                    "Converted from refund #{$refund->refund_number}",
                );
            } else {
                // Cash/original-method refund reduces what the business is
                // still counting as outstanding from this customer's orders
                // — same incremental-maintenance caveat as recordPayment().
                $payment->customer()->increment('outstanding_balance', $amount);
            }

            return $refund->fresh();
        });
    }

    public function applyStoreCredit(LaundryOrder $order, string $amount, User $actor, StoreCreditService $storeCreditService): Payment
    {
        return DB::transaction(function () use ($order, $amount, $actor, $storeCreditService) {
            if (bccomp($amount, (string) $order->customer->store_credit_balance, 2) > 0) {
                throw ValidationException::withMessages([
                    'amount' => 'This exceeds the customer\'s available store credit.',
                ]);
            }

            $remainingOnOrder = bcsub(
                (string) $order->total_amount,
                (string) $order->payments()->whereIn('payment_status', ['paid', 'partial'])->sum('amount'),
                2
            );

            if (bccomp($amount, $remainingOnOrder, 2) > 0) {
                throw ValidationException::withMessages([
                    'amount' => "This exceeds the order's remaining balance ({$remainingOnOrder}).",
                ]);
            }

            $payment = $this->recordPayment($order, [
                'amount' => $amount,
                'payment_method' => 'store_credit',
                'reference' => null,
            ], $actor);

            $storeCreditService->debit($order->customer, $amount, 'order_payment_usage', $actor, 'payments', $payment->id, "Applied to order {$order->order_number}");

            $order->increment('store_credit_applied_amount', $amount);

            return $payment;
        });
    }

    private function generateReceipt(LaundryOrder $order, Payment $payment, User $actor): Receipt
    {
        $businessName = Setting::where('setting_group', 'general')
            ->where('setting_key', 'business_name')
            ->value('setting_value') ?? config('app.name');

        return Receipt::create([
            'receipt_number' => $this->generateNumber('RCP', Receipt::class, 'receipt_number'),
            'laundry_order_id' => $order->id,
            'payment_id' => $payment->id,
            'customer_id' => $order->customer_id,
            'status' => 'generated',
            'subtotal_snapshot' => $order->subtotal_amount,
            'discount_snapshot' => $order->discount_amount,
            'delivery_fee_snapshot' => $order->delivery_fee_amount,
            'store_credit_used_snapshot' => $order->store_credit_applied_amount,
            'total_snapshot' => $order->total_amount,
            'business_name_snapshot' => $businessName,
            'generated_by' => $actor->id,
            'generated_at' => now(),
        ]);
    }

    public function cancelReceipt(Receipt $receipt, string $reason): Receipt
    {
        $receipt->update(['status' => 'cancelled', 'cancelled_reason' => $reason]);

        return $receipt->fresh();
    }

    /**
     * trg_payments_prevent_paid_tamper blocks changing the amount/method of
     * a settled payment at the database level. Nothing in this build's UI
     * offers an "edit payment" action — by design, the correction path is
     * always a refund, never an in-place edit — but this exists so that if
     * anything (a bug, a future admin tool) ever attempts it, the failure
     * reads as guidance instead of a raw SQLSTATE dump. Directly tested
     * per IMPLEMENTATION_PLAN.md Phase 8's "write the copy now" note.
     */
    public function translateImmutabilityError(QueryException $e): ?string
    {
        if ($e->getCode() === '45000' && str_contains($e->getMessage(), 'BR-063')) {
            return 'This payment is already settled — issue a refund instead of editing it.';
        }

        return null;
    }

    private function generateNumber(string $prefix, string $modelClass, string $column): string
    {
        do {
            $candidate = $prefix.'-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
        } while ($modelClass::where($column, $candidate)->exists());

        return $candidate;
    }
}
