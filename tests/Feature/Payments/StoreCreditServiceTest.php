<?php

namespace Tests\Feature\Payments;

use App\Models\Customer;
use App\Models\User;
use App\Services\StoreCreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StoreCreditServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->customer = Customer::create(['name' => 'Credit Customer', 'phone' => '0700000020', 'customer_type' => 'walk_in', 'status' => 'active']);
    }

    public function test_credit_increases_the_customers_balance_via_the_trigger_synced_column(): void
    {
        $transaction = app(StoreCreditService::class)->credit($this->customer, '30.00', 'manual_adjustment', $this->user);

        $this->assertSame('30.00', (string) $transaction->balance_after);
        $this->assertSame('30.00', (string) $this->customer->fresh()->store_credit_balance);
    }

    public function test_debit_decreases_the_balance(): void
    {
        app(StoreCreditService::class)->credit($this->customer, '30.00', 'manual_adjustment', $this->user);

        $transaction = app(StoreCreditService::class)->debit($this->customer, '10.00', 'order_payment_usage', $this->user);

        $this->assertSame('20.00', (string) $transaction->balance_after);
        $this->assertSame('20.00', (string) $this->customer->fresh()->store_credit_balance);
    }

    public function test_a_debit_that_would_drive_the_balance_negative_is_rejected(): void
    {
        app(StoreCreditService::class)->credit($this->customer, '10.00', 'manual_adjustment', $this->user);

        try {
            app(StoreCreditService::class)->debit($this->customer, '10.01', 'order_payment_usage', $this->user);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertSame('10.00', (string) $this->customer->fresh()->store_credit_balance);
        }
    }
}
