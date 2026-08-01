<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\LaundryOrder;
use App\Models\LaundryOrderStageHistory;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Owns both halves of the subscription fulfillment cycle: generating
 * scheduled collections per subscription frequency, and converting a
 * collection into a laundry order under a row lock (Database_Design_Document
 * §6's other named FOR-UPDATE boundary, alongside store-credit writes).
 */
class CollectionService
{
    /**
     * Days between collections per frequency_type — "N times per month",
     * matching the monthly_1..monthly_4 naming (schema.sql leaves the exact
     * cadence undefined beyond the label, so this is the concrete
     * interpretation this build commits to). Custom subscriptions supply
     * their own {"interval_days": N} in custom_frequency_config.
     */
    private const FREQUENCY_DAYS = [
        'monthly_1' => 30,
        'monthly_2' => 15,
        'monthly_3' => 10,
        'monthly_4' => 7,
    ];

    public function intervalDaysFor(Subscription $subscription): int
    {
        if ($subscription->frequency_type === 'custom') {
            $days = (int) ($subscription->custom_frequency_config['interval_days'] ?? 0);

            return $days > 0 ? $days : 30;
        }

        return self::FREQUENCY_DAYS[$subscription->frequency_type] ?? 30;
    }

    /**
     * Creates a `collections` row for every active subscription whose
     * next_collection_date has arrived, then advances that subscription's
     * next_collection_date by its own interval. Idempotent per day — a
     * subscription already collected for its current due date won't be
     * double-generated on a second run the same day, since the date is
     * advanced immediately after generating.
     */
    public function generateDueCollections(?Carbon $asOf = null): int
    {
        $asOf ??= now();
        $generated = 0;

        Subscription::where('status', 'active')
            ->whereNotNull('next_collection_date')
            ->where('next_collection_date', '<=', $asOf->toDateString())
            ->each(function (Subscription $subscription) use (&$generated) {
                DB::transaction(function () use ($subscription) {
                    Collection::create([
                        'customer_id' => $subscription->customer_id,
                        'subscription_id' => $subscription->id,
                        'scheduled_date' => $subscription->next_collection_date,
                        'package_quantity' => $subscription->subscriptionPackages()->sum('quantity'),
                        'status' => 'scheduled',
                    ]);

                    $subscription->update([
                        'next_collection_date' => Carbon::parse($subscription->next_collection_date)
                            ->addDays($this->intervalDaysFor($subscription)),
                    ]);
                });

                $generated++;
            });

        return $generated;
    }

    /**
     * Converts a collection into a laundry order. Locks the collection row
     * for the duration of the transaction so a concurrent double-click
     * can't create two orders for the same collection — the second caller
     * blocks on the row lock, then (once the first commits) sees
     * laundry_order_id already set and fails cleanly instead of duplicating
     * the order. Wrapped in a deadlock-retry loop per
     * MASTER_SPECIFICATION.md §6.3/§9 Phase 3 — the one piece of application
     * code that document explicitly flags as never having been written.
     */
    public function convertToOrder(Collection $collection, User $actor): LaundryOrder
    {
        return $this->retryOnDeadlock(function () use ($collection, $actor) {
            return DB::transaction(function () use ($collection, $actor) {
                $locked = Collection::where('id', $collection->id)->lockForUpdate()->firstOrFail();

                if ($locked->laundry_order_id !== null) {
                    throw new RuntimeException("This collection was already converted to order #{$locked->laundry_order_id}.");
                }

                $order = $this->createOrderShell($locked, $actor);

                // Fires trg_collections_sync_order_subscription: since the
                // fresh order's subscription_id is still null, this auto-fills
                // it from $locked->subscription_id — the trigger's happy path,
                // exercised for real on every conversion, not just in a test.
                $locked->update([
                    'laundry_order_id' => $order->id,
                    'collection_date' => $locked->collection_date ?? now()->toDateString(),
                    'status' => 'laundry_created',
                ]);

                return $order->fresh();
            });
        });
    }

    private function createOrderShell(Collection $collection, User $actor): LaundryOrder
    {
        $subscription = $collection->subscription()->with('subscriptionPackages.package')->first();

        $order = LaundryOrder::create([
            'order_number' => $this->generateOrderNumber(),
            'customer_id' => $collection->customer_id,
            'status' => 'waiting_queue',
            'delivery_type' => 'pickup',
            'instructions' => 'No special instructions',
            'created_by' => $actor->id,
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

        // Package lines only — no items yet. Per a subscription's own
        // design, the actual clothes get catalogued by staff once the
        // laundry physically arrives, same as the collection itself
        // doesn't know item-level detail ahead of pickup.
        $subtotal = '0';

        foreach ($subscription?->subscriptionPackages ?? [] as $subPackage) {
            $lineTotal = bcmul((string) $subPackage->package->price, (string) $subPackage->quantity, 2);
            $subtotal = bcadd($subtotal, $lineTotal, 2);

            $order->packages()->create([
                'package_id' => $subPackage->package_id,
                'quantity' => $subPackage->quantity,
                'unit_price_snapshot' => $subPackage->package->price,
                'line_total' => $lineTotal,
            ]);
        }

        $order->update(['subtotal_amount' => $subtotal, 'total_amount' => $subtotal]);

        return $order->fresh();
    }

    private function retryOnDeadlock(callable $callback, int $maxAttempts = 3)
    {
        $attempt = 0;

        while (true) {
            try {
                return $callback();
            } catch (QueryException $e) {
                $attempt++;
                $mysqlErrorCode = $e->errorInfo[1] ?? null;
                $isTransient = in_array($mysqlErrorCode, [1213, 1205], true); // deadlock, lock wait timeout

                if (! $isTransient || $attempt >= $maxAttempts) {
                    throw $e;
                }

                usleep(50_000 * $attempt);
            }
        }
    }

    private function generateOrderNumber(): string
    {
        do {
            $candidate = 'ORD-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
        } while (LaundryOrder::where('order_number', $candidate)->exists());

        return $candidate;
    }
}
