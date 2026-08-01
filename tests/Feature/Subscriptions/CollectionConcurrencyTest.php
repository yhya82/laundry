<?php

namespace Tests\Feature\Subscriptions;

use App\Models\Collection;
use App\Models\Customer;
use App\Models\LaundryOrder;
use App\Models\Subscription;
use App\Models\User;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Deliberately does NOT use RefreshDatabase. That trait wraps each test in
 * an uncommitted transaction on the test process's own connection — the
 * two child processes spawned below open their own separate database
 * connections and would see none of that setup data (transaction
 * isolation), making the whole point of this test (real, separate-process
 * lock contention) impossible to observe. Every row this test creates is
 * cleaned up explicitly in tearDown() instead.
 */
class CollectionConcurrencyTest extends TestCase
{
    private ?Collection $collection = null;

    private ?Customer $customer = null;

    private ?Subscription $subscription = null;

    private ?User $user = null;

    protected function tearDown(): void
    {
        // fk_collections_order is ON DELETE RESTRICT — the collection (the
        // FK's child side) must go before the laundry_order it points to.
        $this->collection?->delete();
        LaundryOrder::where('customer_id', $this->customer?->id)->delete();
        $this->subscription?->delete();
        $this->customer?->delete();
        $this->user?->delete();

        parent::tearDown();
    }

    /**
     * The exit criteria's own words: "even under a simulated concurrent
     * double-click." Two genuinely separate OS processes race for the same
     * collection's row lock — not simulated within a single PHP process,
     * which can't reproduce real lock contention.
     */
    public function test_two_concurrent_processes_converting_the_same_collection_only_one_succeeds(): void
    {
        $this->user = User::factory()->create();
        $this->customer = Customer::create(['name' => 'Concurrency Customer', 'phone' => '0700000099', 'customer_type' => 'subscription', 'status' => 'active']);
        $this->subscription = Subscription::create([
            'customer_id' => $this->customer->id,
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'frequency_type' => 'monthly_1',
        ]);
        $this->collection = Collection::create([
            'customer_id' => $this->customer->id,
            'subscription_id' => $this->subscription->id,
            'scheduled_date' => now()->toDateString(),
            'status' => 'scheduled',
        ]);

        $artisan = base_path('artisan');
        $php = PHP_BINARY;

        $processA = new Process([$php, $artisan, 'app:convert-collection', (string) $this->collection->id, '--user='.$this->user->id], base_path());
        $processB = new Process([$php, $artisan, 'app:convert-collection', (string) $this->collection->id, '--user='.$this->user->id], base_path());

        // Start both without waiting, so they genuinely overlap.
        $processA->start();
        $processB->start();

        while ($processA->isRunning() || $processB->isRunning()) {
            usleep(20_000);
        }

        $outputs = [$processA->getOutput().$processA->getErrorOutput(), $processB->getOutput().$processB->getErrorOutput()];
        $successes = array_filter($outputs, fn ($o) => str_contains($o, 'CONVERSION_SUCCESS:'));
        $rejections = array_filter($outputs, fn ($o) => str_contains($o, 'REJECTED_ALREADY_CONVERTED'));

        $this->assertCount(1, $successes, 'Expected exactly one process to succeed. Outputs: '.implode(' | ', $outputs));
        $this->assertCount(1, $rejections, 'Expected exactly one process to be cleanly rejected. Outputs: '.implode(' | ', $outputs));

        // Only one order was ever created for this collection — no duplicate,
        // and uq_collections_order stays satisfiable.
        $this->assertSame(1, LaundryOrder::where('customer_id', $this->customer->id)->count());
        $this->assertNotNull($this->collection->fresh()->laundry_order_id);
    }
}
