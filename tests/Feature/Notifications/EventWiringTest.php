<?php

namespace Tests\Feature\Notifications;

use App\Models\ClothingType;
use App\Models\Customer;
use App\Models\DamageType;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\Notification;
use App\Models\Package;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Services\CollectionService;
use App\Services\DamageService;
use App\Services\DeliveryService;
use App\Services\ExpenseService;
use App\Services\LaundryOrderService;
use App\Services\NotificationService;
use App\Services\PaymentService;
use App\Services\StoreCreditService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves every notification type in NotificationService fires at least
 * once through its real business-event path — the literal Phase 12 exit
 * criteria ("every event type ... fires at least once in a manual
 * end-to-end test"), automated rather than manual.
 */
class EventWiringTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Customer $customer;

    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingSeeder::class);

        $this->manager = User::factory()->create();
        $this->manager->roles()->attach(Role::where('slug', 'manager')->first()->id, ['is_primary' => true]);

        $this->customer = Customer::create(['name' => 'Wiring Customer', 'phone' => '0700000300', 'email' => 'wiring@example.com', 'customer_type' => 'walk_in', 'status' => 'active']);
        $this->package = Package::create(['name' => 'Wiring Pkg', 'price' => 20, 'maximum_clothes' => 5, 'priority' => 'normal', 'status' => 'active']);
    }

    private function assertNotified(string $recipientType, int $recipientId, string $type): void
    {
        $this->assertDatabaseHas('notifications', [
            'recipient_type' => $recipientType,
            'recipient_id' => $recipientId,
            'type' => $type,
        ]);
    }

    public function test_order_ready_and_cancelled_fire(): void
    {
        $order = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'cart' => [['package_id' => $this->package->id, 'items' => []]],
        ], $this->manager);

        $service = app(LaundryOrderService::class);
        foreach (array_slice(LaundryOrderService::STAGES, 1) as $stage) {
            if ($stage === 'ready') {
                break;
            }
            $order = $service->advanceStage($order, null, $this->manager);
        }
        $order = $service->advanceStage($order, null, $this->manager); // -> ready

        $this->assertNotified('customer', $this->customer->id, NotificationService::TYPE_ORDER_READY);

        $order2 = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'cart' => [['package_id' => $this->package->id, 'items' => []]],
        ], $this->manager);
        app(LaundryOrderService::class)->cancelOrder($order2, 'Testing cancellation', $this->manager);

        $this->assertNotified('customer', $this->customer->id, NotificationService::TYPE_ORDER_CANCELLED);
    }

    public function test_payment_received_and_refund_processed_fire(): void
    {
        $order = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'cart' => [['package_id' => $this->package->id, 'items' => []]],
            'payment' => ['amount' => '20.00', 'payment_method' => 'cash', 'reference' => null],
        ], $this->manager);

        $this->assertNotified('customer', $this->customer->id, NotificationService::TYPE_PAYMENT_RECEIVED);

        $payment = $order->fresh()->payments->first();
        app(PaymentService::class)->refundPayment($payment, '5.00', 'Testing refund', $this->manager);

        $this->assertNotified('customer', $this->customer->id, NotificationService::TYPE_REFUND_PROCESSED);
    }

    public function test_damage_resolved_and_rejected_fire(): void
    {
        $shirt = ClothingType::create(['name' => 'Wiring Shirt', 'status' => 'active']);
        $damageType = DamageType::create(['name' => 'Wiring Damage', 'status' => 'active']);

        $order = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'cart' => [['package_id' => $this->package->id, 'items' => [['clothing_type_id' => $shirt->id, 'quantity' => 1]]]],
        ], $this->manager);
        $itemId = $order->fresh(['packages.items'])->packages->first()->items->first()->id;

        $damageService = app(DamageService::class);

        $report1 = $damageService->createReport($order->fresh(), 'Resolved case', [
            ['laundry_order_item_id' => $itemId, 'damage_type_id' => $damageType->id, 'severity' => 'low', 'description' => null],
        ], $this->manager);
        $report1 = $damageService->approve($report1, 'other', '5.00', '0.00', $this->manager);
        $damageService->resolve($report1, $this->manager, app(StoreCreditService::class));

        $this->assertNotified('customer', $this->customer->id, NotificationService::TYPE_DAMAGE_RESOLVED);

        $order2 = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'cart' => [['package_id' => $this->package->id, 'items' => [['clothing_type_id' => $shirt->id, 'quantity' => 1]]]],
        ], $this->manager);
        $itemId2 = $order2->fresh(['packages.items'])->packages->first()->items->first()->id;

        $report2 = $damageService->createReport($order2->fresh(), 'Rejected case', [
            ['laundry_order_item_id' => $itemId2, 'damage_type_id' => $damageType->id, 'severity' => 'low', 'description' => null],
        ], $this->manager);
        $damageService->reject($report2, $this->manager);

        $this->assertNotified('customer', $this->customer->id, NotificationService::TYPE_DAMAGE_REJECTED);
    }

    public function test_delivery_lifecycle_notifications_fire(): void
    {
        $deliveryUser = User::factory()->create();
        $employee = Employee::create(['user_id' => $deliveryUser->id, 'name' => 'Wiring Courier', 'status' => 'active']);

        $order = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'delivery_type' => 'delivery',
            'cart' => [['package_id' => $this->package->id, 'items' => []]],
        ], $this->manager);

        $deliveryService = app(DeliveryService::class);
        $delivery = $deliveryService->createDelivery($order->fresh(), [
            'address_id' => null, 'address_snapshot' => 'Wiring Address', 'delivery_fee' => '0', 'scheduled_date' => null, 'delivery_instructions' => null,
        ], $this->manager);

        $delivery = $deliveryService->assignStaff($delivery, $employee->id, $this->manager);
        $this->assertNotified('user', $deliveryUser->id, NotificationService::TYPE_DELIVERY_ASSIGNED);

        $delivery = $deliveryService->markPickedUp($delivery, $this->manager);
        $delivery = $deliveryService->markOutForDelivery($delivery, $this->manager);
        $this->assertNotified('customer', $this->customer->id, NotificationService::TYPE_DELIVERY_OUT_FOR_DELIVERY);

        $deliveryService->markDelivered($delivery, $this->manager);
        $this->assertNotified('customer', $this->customer->id, NotificationService::TYPE_DELIVERY_DELIVERED);

        // Separate delivery for the failed path.
        $order2 = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'delivery_type' => 'delivery',
            'cart' => [['package_id' => $this->package->id, 'items' => []]],
        ], $this->manager);
        $delivery2 = $deliveryService->createDelivery($order2->fresh(), [
            'address_id' => null, 'address_snapshot' => 'Wiring Address 2', 'delivery_fee' => '0', 'scheduled_date' => null, 'delivery_instructions' => null,
        ], $this->manager);
        $delivery2 = $deliveryService->assignStaff($delivery2, $employee->id, $this->manager);
        $delivery2 = $deliveryService->markPickedUp($delivery2, $this->manager);
        $delivery2 = $deliveryService->markOutForDelivery($delivery2, $this->manager);
        $deliveryService->markFailed($delivery2, 'Nobody home', $this->manager);

        $this->assertNotified('customer', $this->customer->id, NotificationService::TYPE_DELIVERY_FAILED);
    }

    public function test_expense_pending_approved_and_rejected_fire(): void
    {
        $category = ExpenseCategory::create(['name' => 'Wiring Category']);

        $juniorRole = Role::create(['name' => 'Wiring Junior', 'slug' => 'wiring-junior', 'status' => 'active']);
        $juniorRole->permissions()->attach(Permission::where('slug', 'expenses.create')->first()->id);
        $junior = User::factory()->create();
        $junior->roles()->attach($juniorRole->id, ['is_primary' => true]);

        $expenseService = app(ExpenseService::class);

        $pending = $expenseService->createExpense([
            'title' => 'Big expense', 'category_id' => $category->id, 'amount' => '500.00',
            'payment_method' => 'cash', 'description' => null, 'attachment_path' => null, 'expense_date' => now()->toDateString(),
        ], $junior);

        $this->assertNotified('user', $this->manager->id, NotificationService::TYPE_EXPENSE_PENDING_APPROVAL);

        $expenseService->approve($pending, $this->manager);
        $this->assertNotified('user', $junior->id, NotificationService::TYPE_EXPENSE_APPROVED);

        $pending2 = $expenseService->createExpense([
            'title' => 'Another big expense', 'category_id' => $category->id, 'amount' => '500.00',
            'payment_method' => 'cash', 'description' => null, 'attachment_path' => null, 'expense_date' => now()->toDateString(),
        ], $junior);
        $expenseService->reject($pending2, $this->manager);
        $this->assertNotified('user', $junior->id, NotificationService::TYPE_EXPENSE_REJECTED);
    }

    public function test_collection_scheduled_fires(): void
    {
        $subscription = Subscription::create([
            'customer_id' => $this->customer->id,
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'frequency_type' => 'monthly_1',
            'next_collection_date' => now()->toDateString(),
        ]);

        app(CollectionService::class)->generateDueCollections(Carbon::now());

        $this->assertNotified('customer', $this->customer->id, NotificationService::TYPE_COLLECTION_SCHEDULED);
    }

    public function test_every_notification_service_type_constant_is_exercised_by_this_file(): void
    {
        $reflection = new \ReflectionClass(NotificationService::class);
        $typeConstants = collect($reflection->getConstants())
            ->filter(fn ($value, $name) => str_starts_with($name, 'TYPE_'));

        $firedTypes = Notification::query()->distinct()->pluck('type');

        // This assertion only means something after the other tests in
        // this class have run and committed their rows within the same
        // process — RefreshDatabase isolates each test method's
        // transaction, so this is a static list check instead: every
        // constant this file's other tests assert against exhaustively
        // covers NotificationService's own taxonomy, checked once here so
        // a newly added TYPE_* constant with no wiring test fails loudly.
        $expectedTypes = [
            NotificationService::TYPE_ORDER_READY,
            NotificationService::TYPE_ORDER_CANCELLED,
            NotificationService::TYPE_PAYMENT_RECEIVED,
            NotificationService::TYPE_REFUND_PROCESSED,
            NotificationService::TYPE_DAMAGE_RESOLVED,
            NotificationService::TYPE_DAMAGE_REJECTED,
            NotificationService::TYPE_DELIVERY_ASSIGNED,
            NotificationService::TYPE_DELIVERY_OUT_FOR_DELIVERY,
            NotificationService::TYPE_DELIVERY_DELIVERED,
            NotificationService::TYPE_DELIVERY_FAILED,
            NotificationService::TYPE_EXPENSE_PENDING_APPROVAL,
            NotificationService::TYPE_EXPENSE_APPROVED,
            NotificationService::TYPE_EXPENSE_REJECTED,
            NotificationService::TYPE_COLLECTION_SCHEDULED,
        ];

        $this->assertEqualsCanonicalizing($typeConstants->values()->all(), $expectedTypes);
    }
}
