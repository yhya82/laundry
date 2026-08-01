<?php

namespace Tests\Feature\Delivery;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Delivery;
use App\Models\Employee;
use App\Models\LaundryOrder;
use App\Models\Package;
use App\Models\User;
use App\Services\DeliveryService;
use App\Services\LaundryOrderService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class DeliveryServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Customer $customer;

    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actor = User::factory()->create();
        $this->customer = Customer::create(['name' => 'Delivery Customer', 'phone' => '0700000100', 'customer_type' => 'walk_in', 'status' => 'active']);
        $this->package = Package::create(['name' => 'Wash Pkg', 'price' => 30, 'maximum_clothes' => 5, 'priority' => 'normal', 'status' => 'active']);
    }

    private function createOrder(string $deliveryType = 'delivery'): LaundryOrder
    {
        return app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'delivery_type' => $deliveryType,
            'cart' => [['package_id' => $this->package->id, 'items' => []]],
        ], $this->actor);
    }

    public function test_creates_a_delivery_and_syncs_the_order_fee_and_total(): void
    {
        $order = $this->createOrder();

        $delivery = app(DeliveryService::class)->createDelivery($order, [
            'address_id' => null,
            'address_snapshot' => '12 Market Rd, Lagos',
            'delivery_fee' => '5.00',
            'scheduled_date' => null,
            'delivery_instructions' => null,
        ], $this->actor);

        $this->assertSame('pending', $delivery->status);
        $this->assertSame('5.00', (string) $order->fresh()->delivery_fee_amount);
        $this->assertSame('35.00', (string) $order->fresh()->total_amount);
        $this->assertSame('35.00', (string) $this->customer->fresh()->outstanding_balance);
        $this->assertDatabaseHas('delivery_status_history', ['delivery_id' => $delivery->id, 'status' => 'pending']);
    }

    public function test_creating_a_delivery_against_a_pickup_order_is_rejected(): void
    {
        $order = $this->createOrder('pickup');

        $this->expectException(ValidationException::class);

        app(DeliveryService::class)->createDelivery($order, [
            'address_id' => null,
            'address_snapshot' => 'Somewhere',
            'delivery_fee' => '0',
            'scheduled_date' => null,
            'delivery_instructions' => null,
        ], $this->actor);
    }

    public function test_the_trigger_itself_rejects_a_delivery_row_against_a_pickup_order(): void
    {
        $order = $this->createOrder('pickup');

        $this->expectExceptionMessageMatches('/cannot be created for an order not marked/');

        Delivery::create([
            'laundry_order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'delivery_type' => 'delivery',
            'status' => 'pending',
        ]);
    }

    public function test_a_second_delivery_against_the_same_order_is_rejected(): void
    {
        $order = $this->createOrder();
        app(DeliveryService::class)->createDelivery($order, [
            'address_id' => null, 'address_snapshot' => 'A', 'delivery_fee' => '0', 'scheduled_date' => null, 'delivery_instructions' => null,
        ], $this->actor);

        $this->expectException(ValidationException::class);

        app(DeliveryService::class)->createDelivery($order->fresh(), [
            'address_id' => null, 'address_snapshot' => 'B', 'delivery_fee' => '0', 'scheduled_date' => null, 'delivery_instructions' => null,
        ], $this->actor);
    }

    public function test_using_a_saved_address_snapshots_it(): void
    {
        $order = $this->createOrder();
        $address = CustomerAddress::create(['customer_id' => $this->customer->id, 'street' => '5 Harbor Ave', 'area' => 'Ikoyi', 'city' => 'Lagos']);

        $delivery = app(DeliveryService::class)->createDelivery($order, [
            'address_id' => $address->id,
            'address_snapshot' => null,
            'delivery_fee' => '0',
            'scheduled_date' => null,
            'delivery_instructions' => null,
        ], $this->actor);

        $this->assertSame('5 Harbor Ave, Ikoyi, Lagos', $delivery->address_snapshot);
    }

    public function test_full_lifecycle_through_delivered(): void
    {
        $order = $this->createOrder();
        $employee = Employee::create(['name' => 'Courier Joe', 'status' => 'active']);
        $service = app(DeliveryService::class);

        $delivery = $service->createDelivery($order, [
            'address_id' => null, 'address_snapshot' => 'A', 'delivery_fee' => '0', 'scheduled_date' => null, 'delivery_instructions' => null,
        ], $this->actor);

        $delivery = $service->schedule($delivery, '2026-08-10', $this->actor);
        $this->assertSame('scheduled', $delivery->status);

        $delivery = $service->assignStaff($delivery, $employee->id, $this->actor);
        $this->assertSame('assigned', $delivery->status);
        $this->assertSame($employee->id, $delivery->assigned_staff_id);

        $delivery = $service->markPickedUp($delivery, $this->actor);
        $this->assertSame('picked_up', $delivery->status);

        $delivery = $service->markOutForDelivery($delivery, $this->actor);
        $this->assertSame('out_for_delivery', $delivery->status);

        $delivery = $service->markDelivered($delivery, $this->actor);
        $this->assertSame('delivered', $delivery->status);
        $this->assertNotNull($delivery->completed_date);

        $this->assertSame(6, $delivery->statusHistory()->count());
    }

    public function test_failure_and_reschedule_flow(): void
    {
        $order = $this->createOrder();
        $employee = Employee::create(['name' => 'Courier Jane', 'status' => 'active']);
        $service = app(DeliveryService::class);

        $delivery = $service->createDelivery($order, [
            'address_id' => null, 'address_snapshot' => 'A', 'delivery_fee' => '0', 'scheduled_date' => null, 'delivery_instructions' => null,
        ], $this->actor);
        $delivery = $service->assignStaff($delivery, $employee->id, $this->actor);
        $delivery = $service->markPickedUp($delivery, $this->actor);
        $delivery = $service->markOutForDelivery($delivery, $this->actor);
        $delivery = $service->markFailed($delivery, 'Customer not home', $this->actor);

        $this->assertSame('failed', $delivery->status);
        $this->assertSame('Customer not home', $delivery->failure_reason);

        $delivery = $service->reschedule($delivery, '2026-08-15', $this->actor);

        $this->assertSame('scheduled', $delivery->status);
        $this->assertNull($delivery->failure_reason);
        $this->assertSame('2026-08-15', $delivery->scheduled_date->toDateString());
    }

    public function test_cancelling_a_delivery(): void
    {
        $order = $this->createOrder();
        $service = app(DeliveryService::class);

        $delivery = $service->createDelivery($order, [
            'address_id' => null, 'address_snapshot' => 'A', 'delivery_fee' => '0', 'scheduled_date' => null, 'delivery_instructions' => null,
        ], $this->actor);

        $delivery = $service->cancel($delivery, 'Customer changed mind', $this->actor);

        $this->assertSame('cancelled', $delivery->status);
    }

    public function test_an_illegal_transition_is_rejected(): void
    {
        $order = $this->createOrder();
        $service = app(DeliveryService::class);

        $delivery = $service->createDelivery($order, [
            'address_id' => null, 'address_snapshot' => 'A', 'delivery_fee' => '0', 'scheduled_date' => null, 'delivery_instructions' => null,
        ], $this->actor);

        $this->expectException(RuntimeException::class);

        $service->markDelivered($delivery, $this->actor);
    }

    public function test_translates_the_order_type_trigger_error(): void
    {
        $order = $this->createOrder('pickup');
        $service = app(DeliveryService::class);

        try {
            Delivery::create([
                'laundry_order_id' => $order->id,
                'customer_id' => $this->customer->id,
                'delivery_type' => 'delivery',
                'status' => 'pending',
            ]);
            $this->fail('Expected trg_deliveries_check_order_type to reject this insert.');
        } catch (QueryException $e) {
            $friendly = $service->translateOrderTypeError($e);
            $this->assertSame('This order is not marked for delivery — only pickup is available.', $friendly);
        }
    }
}
