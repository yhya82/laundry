<?php

namespace Tests\Feature\Damage;

use App\Models\ClothingType;
use App\Models\Customer;
use App\Models\DamageReport;
use App\Models\DamageType;
use App\Models\Package;
use App\Models\User;
use App\Services\DamageService;
use App\Services\LaundryOrderService;
use App\Services\StoreCreditService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class DamageServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Customer $customer;

    private DamageType $damageType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actor = User::factory()->create();
        $this->customer = Customer::create(['name' => 'Damage Customer', 'phone' => '0700000060', 'customer_type' => 'walk_in', 'status' => 'active']);
        $this->damageType = DamageType::create(['name' => 'Torn / Ripped', 'status' => 'active']);
    }

    private static int $orderSeq = 0;

    private function createOrderWithItem(): array
    {
        $n = ++self::$orderSeq;
        $package = Package::create(['name' => "Wash Pkg {$n}", 'price' => 20, 'maximum_clothes' => 5, 'priority' => 'normal', 'status' => 'active']);
        $shirt = ClothingType::create(['name' => "Shirt {$n}", 'status' => 'active']);

        $order = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'cart' => [
                ['package_id' => $package->id, 'items' => [['clothing_type_id' => $shirt->id, 'quantity' => 2]]],
            ],
        ], $this->actor);

        $item = $order->fresh(['packages.items'])->packages->first()->items->first();

        return [$order->fresh(), $item];
    }

    public function test_creates_a_report_with_items(): void
    {
        [$order, $item] = $this->createOrderWithItem();

        $report = app(DamageService::class)->createReport($order, 'Shirt came back torn', [
            ['laundry_order_item_id' => $item->id, 'damage_type_id' => $this->damageType->id, 'severity' => 'medium', 'description' => null],
        ], $this->actor);

        $this->assertSame('reported', $report->status);
        $this->assertCount(1, $report->items);
        $this->assertSame('medium', $report->items->first()->severity);
    }

    public function test_reporting_against_a_cancelled_order_is_rejected(): void
    {
        [$order, $item] = $this->createOrderWithItem();
        app(LaundryOrderService::class)->cancelOrder($order, 'Customer cancelled', $this->actor);

        $this->expectException(ValidationException::class);

        app(DamageService::class)->createReport($order->fresh(), 'Too late', [
            ['laundry_order_item_id' => $item->id, 'damage_type_id' => $this->damageType->id, 'severity' => 'low', 'description' => null],
        ], $this->actor);
    }

    public function test_the_trigger_itself_rejects_a_report_against_a_cancelled_order(): void
    {
        [$order, $item] = $this->createOrderWithItem();
        app(LaundryOrderService::class)->cancelOrder($order, 'Customer cancelled', $this->actor);

        $this->expectExceptionMessageMatches('/BR-045 violation/');

        DamageReport::create([
            'laundry_order_id' => $order->fresh()->id,
            'customer_id' => $this->customer->id,
            'status' => 'reported',
            'description' => 'Bypassing the service',
            'created_by' => $this->actor->id,
        ]);
    }

    public function test_an_item_that_does_not_belong_to_the_order_is_rejected(): void
    {
        [$order] = $this->createOrderWithItem();
        [, $otherItem] = $this->createOrderWithItem();

        $this->expectException(ValidationException::class);

        app(DamageService::class)->createReport($order, 'Wrong item', [
            ['laundry_order_item_id' => $otherItem->id, 'damage_type_id' => $this->damageType->id, 'severity' => 'low', 'description' => null],
        ], $this->actor);
    }

    public function test_evidence_upload_stores_the_file_and_records_it(): void
    {
        Storage::fake('public');

        [$order, $item] = $this->createOrderWithItem();
        $report = app(DamageService::class)->createReport($order, 'Stained', [
            ['laundry_order_item_id' => $item->id, 'damage_type_id' => $this->damageType->id, 'severity' => 'high', 'description' => null],
        ], $this->actor);

        $file = UploadedFile::fake()->image('evidence.jpg');
        $evidence = app(DamageService::class)->addEvidence($report, $file, $this->actor);

        Storage::disk('public')->assertExists($evidence->file_path);
        $this->assertSame($this->actor->id, $evidence->uploaded_by);
    }

    public function test_full_resolution_flow_credits_store_credit_through_the_real_service(): void
    {
        [$order, $item] = $this->createOrderWithItem();
        $report = app(DamageService::class)->createReport($order, 'Ruined shirt', [
            ['laundry_order_item_id' => $item->id, 'damage_type_id' => $this->damageType->id, 'severity' => 'critical', 'description' => null],
        ], $this->actor);

        $service = app(DamageService::class);
        $report = $service->approve($report, 'store_credit', '0.00', '15.00', $this->actor);
        $this->assertSame('approved', $report->status);

        $report = $service->resolve($report, $this->actor, app(StoreCreditService::class));

        $this->assertSame('resolved', $report->status);
        $this->assertSame('15.00', (string) $this->customer->fresh()->store_credit_balance);
        $this->assertDatabaseHas('store_credit_transactions', [
            'customer_id' => $this->customer->id,
            'source_type' => 'damage_compensation',
            'source_table' => 'damage_reports',
            'source_id' => $report->id,
        ]);
    }

    public function test_resolving_before_approval_is_rejected(): void
    {
        [$order, $item] = $this->createOrderWithItem();
        $report = app(DamageService::class)->createReport($order, 'Not yet approved', [
            ['laundry_order_item_id' => $item->id, 'damage_type_id' => $this->damageType->id, 'severity' => 'low', 'description' => null],
        ], $this->actor);

        $this->expectException(RuntimeException::class);

        app(DamageService::class)->resolve($report, $this->actor, app(StoreCreditService::class));
    }

    public function test_rejecting_a_report(): void
    {
        [$order, $item] = $this->createOrderWithItem();
        $report = app(DamageService::class)->createReport($order, 'Not our fault', [
            ['laundry_order_item_id' => $item->id, 'damage_type_id' => $this->damageType->id, 'severity' => 'low', 'description' => null],
        ], $this->actor);

        $report = app(DamageService::class)->reject($report, $this->actor);

        $this->assertSame('rejected', $report->status);
        $this->assertSame($this->actor->id, $report->approved_by);
    }

    public function test_a_resolved_reports_compensation_is_locked_by_the_trigger_and_translated_to_a_friendly_message(): void
    {
        [$order, $item] = $this->createOrderWithItem();
        $service = app(DamageService::class);
        $report = $service->createReport($order, 'Locked after resolve', [
            ['laundry_order_item_id' => $item->id, 'damage_type_id' => $this->damageType->id, 'severity' => 'medium', 'description' => null],
        ], $this->actor);

        $report = $service->approve($report, 'other', '5.00', '0.00', $this->actor);
        $report = $service->resolve($report, $this->actor, app(StoreCreditService::class));

        try {
            $report->update(['cash_compensation_amount' => '999.00']);
            $this->fail('Expected trg_damage_reports_prevent_resolved_tamper to reject this update.');
        } catch (QueryException $e) {
            $friendly = $service->translateResolvedTamperError($e);
            $this->assertSame('This damage report is already resolved — its resolution and compensation can no longer be changed.', $friendly);
        }
    }
}
