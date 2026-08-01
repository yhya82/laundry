<?php

namespace Tests\Feature\Orders;

use App\Models\ClothingType;
use App\Models\Customer;
use App\Models\DiscountTemplate;
use App\Models\Package;
use App\Models\Role;
use App\Models\User;
use App\Services\LaundryOrderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LaundryOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private User $manager;

    private Customer $customer;

    private Package $package;

    private ClothingType $shirt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cashier = User::factory()->create();
        $this->cashier->roles()->attach(Role::where('slug', 'cashier')->first()->id, ['is_primary' => true]);

        $this->manager = User::factory()->create();
        $this->manager->roles()->attach(Role::where('slug', 'manager')->first()->id, ['is_primary' => true]);

        $this->customer = Customer::create(['name' => 'Test Customer', 'phone' => '0700000001', 'customer_type' => 'walk_in', 'status' => 'active']);
        $this->package = Package::create(['name' => 'Standard Wash', 'price' => 20, 'maximum_clothes' => 5, 'priority' => 'normal', 'status' => 'active']);
        $this->shirt = ClothingType::create(['name' => 'Shirt', 'status' => 'active']);
    }

    public function test_creates_a_full_order_with_packages_items_payment_and_receipt_in_one_transaction(): void
    {
        $order = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'instructions' => 'Handle with care',
            'cart' => [
                ['package_id' => $this->package->id, 'items' => [['clothing_type_id' => $this->shirt->id, 'quantity' => 3]]],
            ],
            'discount' => null,
            'payment' => ['amount' => '20.00', 'payment_method' => 'cash', 'reference' => null],
        ], $this->cashier);

        $this->assertSame('waiting_queue', $order->status);
        $this->assertSame('20.00', (string) $order->subtotal_amount);
        $this->assertSame('20.00', (string) $order->total_amount);
        $this->assertCount(1, $order->packages);
        $this->assertSame(3, $order->packages->first()->items->sum('quantity'));
        $this->assertCount(1, $order->payments);
        $this->assertSame('paid', $order->payments->first()->payment_status);
        $this->assertNotNull($order->receipt);
        $this->assertSame('20.00', (string) $order->receipt->total_snapshot);

        // Opening stage_history row exists and is still open.
        $this->assertDatabaseHas('laundry_order_stage_history', [
            'laundry_order_id' => $order->id,
            'stage' => 'waiting_queue',
            'completed_at' => null,
        ]);
    }

    public function test_exceeding_a_packages_maximum_clothes_is_rejected_before_hitting_the_database_trigger(): void
    {
        $this->expectException(ValidationException::class);

        app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'cart' => [
                ['package_id' => $this->package->id, 'items' => [['clothing_type_id' => $this->shirt->id, 'quantity' => 6]]],
            ],
        ], $this->cashier);

        $this->assertDatabaseCount('laundry_orders', 0);
    }

    public function test_partial_payment_does_not_generate_a_receipt(): void
    {
        $order = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'cart' => [
                ['package_id' => $this->package->id, 'items' => []],
            ],
            'payment' => ['amount' => '5.00', 'payment_method' => 'cash', 'reference' => null],
        ], $this->cashier);

        $this->assertSame('partial', $order->payments->first()->payment_status);
        $this->assertNull($order->receipt);
    }

    public function test_a_cashier_cannot_apply_a_discount_beyond_the_configured_threshold(): void
    {
        $this->seed(SettingSeeder::class); // discount.max_cashier_discount_percent = 10

        $this->expectException(ValidationException::class);

        app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'cart' => [['package_id' => $this->package->id, 'items' => []]],
            'discount' => ['discount_template_id' => null, 'discount_type' => 'percentage', 'value' => '50', 'reason' => 'Because'],
        ], $this->cashier);
    }

    public function test_a_manager_can_apply_a_discount_beyond_the_cashier_threshold(): void
    {
        $this->seed(SettingSeeder::class);

        $order = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'cart' => [['package_id' => $this->package->id, 'items' => []]],
            'discount' => ['discount_template_id' => null, 'discount_type' => 'percentage', 'value' => '50', 'reason' => 'Manager override'],
        ], $this->manager);

        $this->assertSame('10.00', (string) $order->discount_amount);
        $this->assertSame('10.00', (string) $order->total_amount);
    }

    public function test_a_discount_template_flagged_requires_approval_blocks_a_cashier_even_under_the_percent_threshold(): void
    {
        $template = DiscountTemplate::create([
            'name' => 'VIP', 'discount_type' => 'percentage', 'value' => 5, 'requires_approval' => true, 'status' => 'active',
        ]);

        $this->expectException(ValidationException::class);

        app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'cart' => [['package_id' => $this->package->id, 'items' => []]],
            'discount' => ['discount_template_id' => $template->id, 'discount_type' => 'percentage', 'value' => '5', 'reason' => ''],
        ], $this->cashier);
    }

    public function test_order_priority_is_derived_from_an_express_package_in_the_cart(): void
    {
        $expressPackage = Package::create(['name' => 'Express Wash', 'price' => 30, 'maximum_clothes' => 5, 'priority' => 'express', 'status' => 'active']);

        $order = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'cart' => [['package_id' => $expressPackage->id, 'items' => []]],
        ], $this->cashier);

        $this->assertSame('express', $order->priority);
    }

    public function test_advance_stage_moves_exactly_one_step_and_closes_the_previous_stage_history_row(): void
    {
        $order = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'cart' => [['package_id' => $this->package->id, 'items' => []]],
        ], $this->cashier);

        $service = app(LaundryOrderService::class);
        $order = $service->advanceStage($order, null, $this->cashier);

        $this->assertSame('received', $order->status);

        $waitingQueueRow = $order->stageHistory()->where('stage', 'waiting_queue')->first();
        $this->assertNotNull($waitingQueueRow->completed_at);

        $receivedRow = $order->stageHistory()->where('stage', 'received')->first();
        $this->assertNotNull($receivedRow);
        $this->assertNull($receivedRow->completed_at);
    }

    public function test_skipping_a_stage_directly_against_the_database_is_rejected_by_the_trigger(): void
    {
        $order = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'cart' => [['package_id' => $this->package->id, 'items' => []]],
        ], $this->cashier);

        // Bypasses the service entirely — proves trg_laundry_orders_stage_sequence
        // is the real authority, not just something the service happens to respect.
        $this->expectExceptionMessageMatches('/BR-025 violation/');
        $order->update(['status' => 'washing']);
    }

    public function test_cancel_order_sets_cancelled_status_and_reason(): void
    {
        $order = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'cart' => [['package_id' => $this->package->id, 'items' => []]],
        ], $this->cashier);

        $order = app(LaundryOrderService::class)->cancelOrder($order, 'Customer changed mind', $this->cashier);

        $this->assertSame('cancelled', $order->status);
        $this->assertSame('Customer changed mind', $order->cancelled_reason);
    }
}
