<?php

namespace Tests\Feature\Orders;

use App\Livewire\Orders\Show as OrderShow;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Role;
use App\Models\User;
use App\Services\LaundryOrderService;
use App\Services\StoreCreditService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderShowPaymentsTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $cashier;

    private Customer $customer;

    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->manager = User::factory()->create();
        $this->manager->roles()->attach(Role::where('slug', 'manager')->first()->id, ['is_primary' => true]);

        $this->cashier = User::factory()->create();
        $this->cashier->roles()->attach(Role::where('slug', 'cashier')->first()->id, ['is_primary' => true]);

        $this->customer = Customer::create(['name' => 'Show Test Customer', 'phone' => '0700000030', 'customer_type' => 'walk_in', 'status' => 'active']);
        $this->package = Package::create(['name' => 'Standard Wash', 'price' => 50, 'maximum_clothes' => 5, 'priority' => 'normal', 'status' => 'active']);
    }

    public function test_a_cashier_can_record_a_payment_from_the_order_page(): void
    {
        $order = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'cart' => [['package_id' => $this->package->id, 'items' => []]],
        ], $this->cashier);

        Livewire::actingAs($this->cashier)
            ->test(OrderShow::class, ['order' => $order])
            ->call('openPaymentDrawer')
            ->set('paymentAmount', '50.00')
            ->set('paymentMethod', 'cash')
            ->call('recordPayment')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('payments', ['laundry_order_id' => $order->id, 'amount' => '50.00', 'payment_status' => 'paid']);
    }

    public function test_a_cashier_cannot_see_the_refund_action_but_a_manager_can(): void
    {
        $order = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'cart' => [['package_id' => $this->package->id, 'items' => []]],
            'payment' => ['amount' => '50.00', 'payment_method' => 'cash', 'reference' => null],
        ], $this->cashier);

        Livewire::actingAs($this->cashier)
            ->test(OrderShow::class, ['order' => $order])
            ->assertDontSee('Refund');

        Livewire::actingAs($this->manager)
            ->test(OrderShow::class, ['order' => $order])
            ->assertSee('Refund');
    }

    public function test_a_manager_can_issue_a_refund_from_the_order_page(): void
    {
        $order = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'cart' => [['package_id' => $this->package->id, 'items' => []]],
            'payment' => ['amount' => '50.00', 'payment_method' => 'cash', 'reference' => null],
        ], $this->cashier);

        $payment = $order->fresh()->payments->first();

        Livewire::actingAs($this->manager)
            ->test(OrderShow::class, ['order' => $order])
            ->call('openRefundDrawer', $payment->id)
            ->set('refundAmount', '20.00')
            ->set('refundReason', 'Customer complaint')
            ->call('refundPayment')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('refunds', ['payment_id' => $payment->id, 'amount' => '20.00']);
    }

    public function test_a_manager_can_apply_store_credit_from_the_order_page(): void
    {
        $order = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'cart' => [['package_id' => $this->package->id, 'items' => []]],
        ], $this->manager);

        app(StoreCreditService::class)->credit($this->customer, '50.00', 'manual_adjustment', $this->manager);

        Livewire::actingAs($this->manager)
            ->test(OrderShow::class, ['order' => $order])
            ->call('openApplyCreditDrawer')
            ->call('applyStoreCredit')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('payments', ['laundry_order_id' => $order->id, 'payment_method' => 'store_credit', 'payment_status' => 'paid']);
        $this->assertSame('0.00', (string) $this->customer->fresh()->store_credit_balance);
    }

    public function test_a_manager_can_cancel_a_receipt(): void
    {
        $order = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'cart' => [['package_id' => $this->package->id, 'items' => []]],
            'payment' => ['amount' => '50.00', 'payment_method' => 'cash', 'reference' => null],
        ], $this->cashier);

        $receipt = $order->fresh()->receipt;

        Livewire::actingAs($this->manager)
            ->test(OrderShow::class, ['order' => $order])
            ->set('cancelReceiptReason', 'Reprinting under a corrected name')
            ->call('cancelReceipt')
            ->assertHasNoErrors();

        $this->assertSame('cancelled', $receipt->fresh()->status);
    }
}
