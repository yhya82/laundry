<?php

namespace Tests\Feature\Reporting;

use App\Models\ClothingType;
use App\Models\Customer;
use App\Models\DailyStatistic;
use App\Models\DamageType;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use App\Services\CollectionService;
use App\Services\DamageService;
use App\Services\DeliveryService;
use App\Services\ExpenseService;
use App\Services\LaundryOrderService;
use App\Services\PaymentService;
use App\Services\ReportingService;
use App\Services\StoreCreditService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Builds a full day's worth of real activity across every module, then
 * asserts each daily_statistics row against a manually-computed control
 * value — the phase's own exit criteria, automated.
 */
class ReportingServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Customer $customer;

    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actor = User::factory()->create();
        $this->customer = Customer::create(['name' => 'Reporting Customer', 'phone' => '0700000400', 'customer_type' => 'walk_in', 'status' => 'active']);
        $this->package = Package::create(['name' => 'Reporting Pkg', 'price' => 50, 'maximum_clothes' => 5, 'priority' => 'normal', 'status' => 'active']);
    }

    public function test_a_full_days_activity_aggregates_to_the_manually_computed_control_values(): void
    {
        $today = Carbon::today();

        // Order 1: created, paid in full (revenue), advanced all the way to completed.
        $order1 = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'cart' => [['package_id' => $this->package->id, 'items' => []]],
            'payment' => ['amount' => '50.00', 'payment_method' => 'cash', 'reference' => null],
        ], $this->actor);
        $service = app(LaundryOrderService::class);
        foreach (array_slice(LaundryOrderService::STAGES, 1) as $stage) {
            $order1 = $service->advanceStage($order1, null, $this->actor);
        }
        $this->assertSame('completed', $order1->status);

        // Order 2: created, paid, then partially refunded.
        $order2 = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'cart' => [['package_id' => $this->package->id, 'items' => []]],
            'payment' => ['amount' => '50.00', 'payment_method' => 'cash', 'reference' => null],
        ], $this->actor);
        $payment2 = $order2->fresh()->payments->first();
        app(PaymentService::class)->refundPayment($payment2, '10.00', 'Testing', $this->actor);

        // Order 3: created then cancelled.
        $order3 = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'cart' => [['package_id' => $this->package->id, 'items' => []]],
        ], $this->actor);
        app(LaundryOrderService::class)->cancelOrder($order3, 'Testing', $this->actor);

        // Damage report, resolved with 15.00 total compensation.
        $shirt = ClothingType::create(['name' => 'Reporting Shirt', 'status' => 'active']);
        $damageType = DamageType::create(['name' => 'Reporting Damage', 'status' => 'active']);
        $order4 = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'cart' => [['package_id' => $this->package->id, 'items' => [['clothing_type_id' => $shirt->id, 'quantity' => 1]]]],
        ], $this->actor);
        $itemId = $order4->fresh(['packages.items'])->packages->first()->items->first()->id;
        $damageService = app(DamageService::class);
        $report = $damageService->createReport($order4->fresh(), 'Reporting damage', [
            ['laundry_order_item_id' => $itemId, 'damage_type_id' => $damageType->id, 'severity' => 'low', 'description' => null],
        ], $this->actor);
        $report = $damageService->approve($report, 'other', '5.00', '10.00', $this->actor);
        $damageService->resolve($report, $this->actor, app(StoreCreditService::class));

        // Delivery, completed.
        $employee = Employee::create(['name' => 'Reporting Courier', 'status' => 'active']);
        $order5 = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'delivery_type' => 'delivery',
            'cart' => [['package_id' => $this->package->id, 'items' => []]],
        ], $this->actor);
        $deliveryService = app(DeliveryService::class);
        $delivery = $deliveryService->createDelivery($order5->fresh(), [
            'address_id' => null, 'address_snapshot' => 'Reporting Address', 'delivery_fee' => '0', 'scheduled_date' => null, 'delivery_instructions' => null,
        ], $this->actor);
        $delivery = $deliveryService->assignStaff($delivery, $employee->id, $this->actor);
        $delivery = $deliveryService->markPickedUp($delivery, $this->actor);
        $delivery = $deliveryService->markOutForDelivery($delivery, $this->actor);
        $deliveryService->markDelivered($delivery, $this->actor);

        // Expense, auto-approved (under threshold).
        $category = ExpenseCategory::create(['name' => 'Reporting Category']);
        app(ExpenseService::class)->createExpense([
            'title' => 'Reporting expense', 'category_id' => $category->id, 'amount' => '30.00',
            'payment_method' => 'cash', 'description' => null, 'attachment_path' => null, 'expense_date' => $today->toDateString(),
        ], $this->actor);

        // Collection scheduled today.
        $subscription = Subscription::create([
            'customer_id' => $this->customer->id, 'status' => 'active',
            'start_date' => $today->toDateString(), 'frequency_type' => 'monthly_1',
            'next_collection_date' => $today->toDateString(),
        ]);
        app(CollectionService::class)->generateDueCollections($today);

        // --- Manually computed control values ---
        // orders_created: order1..order5 = 5
        // orders_completed: only order1 reached 'completed' = 1
        // orders_cancelled: only order3 = 1
        // revenue_collected: 50 (order1) + 50 (order2) = 100.00
        // refunds_issued: 10.00
        // net_revenue: 90.00
        // expenses_recorded: 30.00
        // new_customers: 1 (created in setUp, same day)
        // damage_reports_filed: 1
        // damage_compensation_paid: 5 + 10 = 15.00
        // deliveries_completed: 1
        // collections_scheduled: 1

        app(ReportingService::class)->aggregateForDate($today);

        $metrics = app(ReportingService::class)->metricsForDate($today);

        // metric_value is decimal(18,2) — counts land as "5.00", not "5".
        $this->assertSame('5.00', $metrics[ReportingService::METRIC_ORDERS_CREATED]);
        $this->assertSame('1.00', $metrics[ReportingService::METRIC_ORDERS_COMPLETED]);
        $this->assertSame('1.00', $metrics[ReportingService::METRIC_ORDERS_CANCELLED]);
        $this->assertSame('100.00', $metrics[ReportingService::METRIC_REVENUE_COLLECTED]);
        $this->assertSame('10.00', $metrics[ReportingService::METRIC_REFUNDS_ISSUED]);
        $this->assertSame('90.00', $metrics[ReportingService::METRIC_NET_REVENUE]);
        $this->assertSame('30.00', $metrics[ReportingService::METRIC_EXPENSES_RECORDED]);
        $this->assertSame('1.00', $metrics[ReportingService::METRIC_NEW_CUSTOMERS]);
        $this->assertSame('1.00', $metrics[ReportingService::METRIC_DAMAGE_REPORTS_FILED]);
        $this->assertSame('15.00', $metrics[ReportingService::METRIC_DAMAGE_COMPENSATION_PAID]);
        $this->assertSame('1.00', $metrics[ReportingService::METRIC_DELIVERIES_COMPLETED]);
        $this->assertSame('1.00', $metrics[ReportingService::METRIC_COLLECTIONS_SCHEDULED]);
    }

    public function test_rerunning_aggregation_for_the_same_date_updates_rather_than_duplicates(): void
    {
        $today = Carbon::today();
        $service = app(ReportingService::class);

        $service->aggregateForDate($today);
        $countAfterFirst = DailyStatistic::whereDate('stat_date', $today)->count();

        app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'cart' => [['package_id' => $this->package->id, 'items' => []]],
        ], $this->actor);

        $service->aggregateForDate($today);
        $countAfterSecond = DailyStatistic::whereDate('stat_date', $today)->count();

        $this->assertSame($countAfterFirst, $countAfterSecond);
        $this->assertSame('1.00', $service->metricsForDate($today)[ReportingService::METRIC_ORDERS_CREATED]);
    }

    public function test_the_scheduled_command_defaults_to_yesterday(): void
    {
        $this->artisan('app:aggregate-daily-statistics')->assertSuccessful();

        $this->assertDatabaseHas('daily_statistics', [
            'stat_date' => now()->subDay()->toDateString(),
            'metric_key' => ReportingService::METRIC_ORDERS_CREATED,
        ]);
    }
}
