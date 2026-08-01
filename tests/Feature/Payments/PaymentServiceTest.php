<?php

namespace Tests\Feature\Payments;

use App\Models\ClothingType;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Role;
use App\Models\User;
use App\Services\LaundryOrderService;
use App\Services\PaymentService;
use App\Services\StoreCreditService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Customer $customer;

    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cashier = User::factory()->create();
        $this->cashier->roles()->attach(Role::where('slug', 'cashier')->first()->id, ['is_primary' => true]);

        $this->customer = Customer::create(['name' => 'Test Customer', 'phone' => '0700000010', 'customer_type' => 'walk_in', 'status' => 'active']);
        $this->package = Package::create(['name' => 'Standard Wash', 'price' => 50, 'maximum_clothes' => 5, 'priority' => 'normal', 'status' => 'active']);
        ClothingType::create(['name' => 'Shirt', 'status' => 'active']);
    }

    public function test_a_second_payment_that_completes_the_order_generates_a_receipt(): void
    {
        $order = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'cart' => [['package_id' => $this->package->id, 'items' => []]],
            'payment' => ['amount' => '20.00', 'payment_method' => 'cash', 'reference' => null],
        ], $this->cashier);

        $this->assertNull($order->fresh()->receipt);

        $payment = app(PaymentService::class)->recordPayment($order->fresh(), [
            'amount' => '30.00', 'payment_method' => 'cash', 'reference' => null,
        ], $this->cashier);

        $this->assertSame('paid', $payment->payment_status);
        $this->assertNotNull($order->fresh()->receipt);
        $this->assertSame('50.00', (string) $order->fresh()->receipt->total_snapshot);
    }

    public function test_a_payment_exceeding_the_order_total_is_rejected_with_a_friendly_message(): void
    {
        $order = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'cart' => [['package_id' => $this->package->id, 'items' => []]],
            'payment' => ['amount' => '20.00', 'payment_method' => 'cash', 'reference' => null],
        ], $this->cashier);

        try {
            app(PaymentService::class)->recordPayment($order->fresh(), [
                'amount' => '40.00', 'payment_method' => 'cash', 'reference' => null,
            ], $this->cashier);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('at most 30.00', $e->validator->errors()->first('amount'));
        }
    }

    public function test_recording_a_payment_decrements_customer_outstanding_balance(): void
    {
        $order = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'cart' => [['package_id' => $this->package->id, 'items' => []]],
        ], $this->cashier);

        $this->assertSame('50.00', (string) $this->customer->fresh()->outstanding_balance);

        app(PaymentService::class)->recordPayment($order->fresh(), [
            'amount' => '50.00', 'payment_method' => 'cash', 'reference' => null,
        ], $this->cashier);

        $this->assertSame('0.00', (string) $this->customer->fresh()->outstanding_balance);
    }

    public function test_a_refund_within_the_remaining_amount_succeeds_and_restores_outstanding_balance(): void
    {
        $order = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'cart' => [['package_id' => $this->package->id, 'items' => []]],
            'payment' => ['amount' => '50.00', 'payment_method' => 'cash', 'reference' => null],
        ], $this->cashier);

        $payment = $order->fresh()->payments->first();
        $this->assertSame('0.00', (string) $this->customer->fresh()->outstanding_balance);

        $refund = app(PaymentService::class)->refundPayment($payment, '20.00', 'Customer complaint', $this->cashier);

        $this->assertSame('20.00', (string) $refund->amount);
        // Stays 'paid' — a partial refund against a fully-paid payment isn't
        // a distinct status in the payment_status enum; refunds().sum() is
        // the source of truth for how much has come back, not this column.
        $this->assertSame('paid', $payment->fresh()->payment_status);
        $this->assertSame('20.00', (string) $this->customer->fresh()->outstanding_balance);
    }

    public function test_refunding_the_full_amount_marks_the_payment_refunded(): void
    {
        $order = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'cart' => [['package_id' => $this->package->id, 'items' => []]],
            'payment' => ['amount' => '50.00', 'payment_method' => 'cash', 'reference' => null],
        ], $this->cashier);

        $payment = $order->fresh()->payments->first();

        app(PaymentService::class)->refundPayment($payment, '50.00', 'Order cancelled', $this->cashier);

        $this->assertSame('refunded', $payment->fresh()->payment_status);
    }

    public function test_a_refund_exceeding_the_remaining_refundable_amount_is_rejected(): void
    {
        $order = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'cart' => [['package_id' => $this->package->id, 'items' => []]],
            'payment' => ['amount' => '50.00', 'payment_method' => 'cash', 'reference' => null],
        ], $this->cashier);

        $payment = $order->fresh()->payments->first();
        app(PaymentService::class)->refundPayment($payment, '30.00', 'Partial issue', $this->cashier);

        try {
            app(PaymentService::class)->refundPayment($payment->fresh(), '25.00', 'Another issue', $this->cashier);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('exceeds the remaining refundable amount (20.00)', $e->validator->errors()->first('amount'));
        }
    }

    public function test_a_refund_converted_to_store_credit_credits_the_customer_instead_of_outstanding_balance(): void
    {
        $order = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'cart' => [['package_id' => $this->package->id, 'items' => []]],
            'payment' => ['amount' => '50.00', 'payment_method' => 'cash', 'reference' => null],
        ], $this->cashier);

        $payment = $order->fresh()->payments->first();
        $before = (string) $this->customer->fresh()->outstanding_balance;

        app(PaymentService::class)->refundPayment($payment, '20.00', 'Prefers credit', $this->cashier, asStoreCredit: true);

        $this->assertSame($before, (string) $this->customer->fresh()->outstanding_balance);
        $this->assertSame('20.00', (string) $this->customer->fresh()->store_credit_balance);
        $this->assertDatabaseHas('store_credit_transactions', [
            'customer_id' => $this->customer->id,
            'direction' => 'credit',
            'source_type' => 'refund_conversion',
        ]);
    }

    public function test_applying_store_credit_beyond_the_customers_balance_is_rejected(): void
    {
        $order = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'cart' => [['package_id' => $this->package->id, 'items' => []]],
        ], $this->cashier);

        app(StoreCreditService::class)->credit($this->customer, '10.00', 'manual_adjustment', $this->cashier);

        $this->expectException(ValidationException::class);

        app(PaymentService::class)->applyStoreCredit($order->fresh(), '20.00', $this->cashier, app(StoreCreditService::class));
    }

    public function test_applying_store_credit_within_balance_pays_the_order_and_debits_the_ledger(): void
    {
        $order = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'cart' => [['package_id' => $this->package->id, 'items' => []]],
        ], $this->cashier);

        app(StoreCreditService::class)->credit($this->customer, '50.00', 'manual_adjustment', $this->cashier);

        $payment = app(PaymentService::class)->applyStoreCredit($order->fresh(), '50.00', $this->cashier, app(StoreCreditService::class));

        $this->assertSame('store_credit', $payment->payment_method);
        $this->assertSame('paid', $payment->payment_status);
        $this->assertSame('0.00', (string) $this->customer->fresh()->store_credit_balance);
        $this->assertSame('50.00', (string) $order->fresh()->store_credit_applied_amount);
        $this->assertNotNull($order->fresh()->receipt);
    }

    public function test_directly_tampering_a_paid_payments_amount_is_rejected_by_the_trigger_and_translated_to_a_friendly_message(): void
    {
        $order = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'cart' => [['package_id' => $this->package->id, 'items' => []]],
            'payment' => ['amount' => '50.00', 'payment_method' => 'cash', 'reference' => null],
        ], $this->cashier);

        $payment = $order->fresh()->payments->first();

        try {
            // Deliberately below the order total (40.00 < 50.00) so this
            // trips BR-063's tamper check specifically, not BR-034's
            // "exceeds order total" check — both are BEFORE UPDATE
            // triggers on payments, and BR-034 fires first.
            $payment->update(['amount' => '40.00']);
            $this->fail('Expected trg_payments_prevent_paid_tamper to reject this update.');
        } catch (QueryException $e) {
            $friendly = app(PaymentService::class)->translateImmutabilityError($e);
            $this->assertSame('This payment is already settled — issue a refund instead of editing it.', $friendly);
        }
    }
}
