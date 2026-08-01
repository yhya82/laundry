<?php

namespace Tests\Feature\Subscriptions;

use App\Models\Customer;
use App\Models\Subscription;
use App\Services\CollectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CollectionGenerationTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::create(['name' => 'Sub Customer', 'phone' => '0700000001', 'customer_type' => 'subscription', 'status' => 'active']);
    }

    #[DataProvider('frequencyProvider')]
    public function test_each_frequency_type_generates_a_collection_and_advances_by_the_right_interval(string $frequency, int $expectedDays): void
    {
        $subscription = Subscription::create([
            'customer_id' => $this->customer->id,
            'status' => 'active',
            'start_date' => now()->subDay()->toDateString(),
            'frequency_type' => $frequency,
            'next_collection_date' => now()->subDay()->toDateString(),
        ]);

        $generated = app(CollectionService::class)->generateDueCollections();

        $this->assertSame(1, $generated);
        $this->assertDatabaseHas('collections', [
            'subscription_id' => $subscription->id,
            'customer_id' => $this->customer->id,
            'status' => 'scheduled',
        ]);

        $subscription->refresh();
        $expectedNext = now()->subDay()->addDays($expectedDays)->toDateString();
        $this->assertSame($expectedNext, $subscription->next_collection_date->toDateString());
    }

    public static function frequencyProvider(): array
    {
        return [
            'once a month' => ['monthly_1', 30],
            'twice a month' => ['monthly_2', 15],
            'three times a month' => ['monthly_3', 10],
            'weekly' => ['monthly_4', 7],
        ];
    }

    public function test_custom_frequency_uses_its_own_interval_days(): void
    {
        $subscription = Subscription::create([
            'customer_id' => $this->customer->id,
            'status' => 'active',
            'start_date' => now()->subDay()->toDateString(),
            'frequency_type' => 'custom',
            'custom_frequency_config' => ['interval_days' => 21],
            'next_collection_date' => now()->subDay()->toDateString(),
        ]);

        app(CollectionService::class)->generateDueCollections();

        $subscription->refresh();
        $this->assertSame(now()->subDay()->addDays(21)->toDateString(), $subscription->next_collection_date->toDateString());
    }

    public function test_a_subscription_not_yet_due_generates_nothing(): void
    {
        Subscription::create([
            'customer_id' => $this->customer->id,
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'frequency_type' => 'monthly_1',
            'next_collection_date' => now()->addWeek()->toDateString(),
        ]);

        $generated = app(CollectionService::class)->generateDueCollections();

        $this->assertSame(0, $generated);
        $this->assertDatabaseCount('collections', 0);
    }

    public function test_a_paused_subscription_does_not_generate_collections(): void
    {
        Subscription::create([
            'customer_id' => $this->customer->id,
            'status' => 'paused',
            'start_date' => now()->subDay()->toDateString(),
            'frequency_type' => 'monthly_1',
            'next_collection_date' => now()->subDay()->toDateString(),
        ]);

        $generated = app(CollectionService::class)->generateDueCollections();

        $this->assertSame(0, $generated);
    }

    public function test_running_generation_twice_the_same_day_does_not_duplicate(): void
    {
        Subscription::create([
            'customer_id' => $this->customer->id,
            'status' => 'active',
            'start_date' => now()->subDay()->toDateString(),
            'frequency_type' => 'monthly_1',
            'next_collection_date' => now()->subDay()->toDateString(),
        ]);

        $service = app(CollectionService::class);
        $service->generateDueCollections();
        $service->generateDueCollections();

        $this->assertDatabaseCount('collections', 1);
    }
}
