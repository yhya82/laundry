<?php

namespace Tests\Feature\Subscriptions;

use App\Models\Collection;
use App\Models\Customer;
use App\Models\LaundryOrder;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use App\Services\CollectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CollectionConversionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    private Subscription $subscription;

    private Collection $collection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->customer = Customer::create(['name' => 'Sub Customer', 'phone' => '0700000002', 'customer_type' => 'subscription', 'status' => 'active']);
        $package = Package::create(['name' => 'Sub Package', 'price' => 25, 'maximum_clothes' => 10, 'priority' => 'normal', 'status' => 'active']);

        $this->subscription = Subscription::create([
            'customer_id' => $this->customer->id,
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'frequency_type' => 'monthly_1',
        ]);
        $this->subscription->subscriptionPackages()->create(['package_id' => $package->id, 'quantity' => 2]);

        $this->collection = Collection::create([
            'customer_id' => $this->customer->id,
            'subscription_id' => $this->subscription->id,
            'scheduled_date' => now()->toDateString(),
            'status' => 'scheduled',
        ]);
    }

    public function test_converting_creates_an_order_with_package_lines_from_the_subscription_and_syncs_subscription_id(): void
    {
        $order = app(CollectionService::class)->convertToOrder($this->collection, $this->user);

        $this->assertSame('waiting_queue', $order->status);
        $this->assertSame(1, $order->packages()->count());
        $this->assertSame(2, $order->packages()->first()->quantity);
        $this->assertSame('50.00', (string) $order->subtotal_amount);

        // trg_collections_sync_order_subscription's happy path: the order's
        // subscription_id was null at creation, auto-filled on the update
        // that links the collection to it.
        $this->assertSame($this->subscription->id, $order->fresh()->subscription_id);

        $this->collection->refresh();
        $this->assertSame($order->id, $this->collection->laundry_order_id);
        $this->assertSame('laundry_created', $this->collection->status);
    }

    public function test_converting_an_already_converted_collection_fails_cleanly(): void
    {
        app(CollectionService::class)->convertToOrder($this->collection, $this->user);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/already converted/');

        app(CollectionService::class)->convertToOrder($this->collection->fresh(), $this->user);

        $this->assertSame(1, LaundryOrder::count());
    }

    /**
     * Direct trigger-level proof (bypassing the service): a collection
     * update that would attach an order already carrying a DIFFERENT
     * subscription_id is rejected by trg_collections_sync_order_subscription
     * itself, not just avoided by the service's own logic.
     */
    public function test_trigger_rejects_linking_a_collection_to_an_order_with_a_conflicting_subscription(): void
    {
        $otherSubscription = Subscription::create([
            'customer_id' => $this->customer->id,
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'frequency_type' => 'monthly_1',
        ]);

        $conflictingOrder = LaundryOrder::create([
            'order_number' => 'ORD-CONFLICT-1',
            'customer_id' => $this->customer->id,
            'subscription_id' => $otherSubscription->id,
            'status' => 'waiting_queue',
            'delivery_type' => 'pickup',
            'instructions' => 'No special instructions',
            'subtotal_amount' => 0,
            'discount_amount' => 0,
            'delivery_fee_amount' => 0,
            'total_amount' => 0,
            'store_credit_applied_amount' => 0,
        ]);

        $this->expectExceptionMessageMatches('/conflicts with the converting collection/');

        $this->collection->update(['laundry_order_id' => $conflictingOrder->id]);
    }
}
