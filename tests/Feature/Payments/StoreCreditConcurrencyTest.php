<?php

namespace Tests\Feature\Payments;

use App\Models\Customer;
use App\Models\StoreCreditTransaction;
use App\Models\User;
use App\Services\StoreCreditService;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Deliberately does NOT use RefreshDatabase — same reasoning as
 * CollectionConcurrencyTest: the two child processes below open their own
 * database connections and need to see real, committed setup data, and the
 * whole point is real cross-process row-lock contention on
 * customers.store_credit_balance, not something a single-process
 * transaction wrapper can reproduce.
 */
class StoreCreditConcurrencyTest extends TestCase
{
    private ?Customer $customer = null;

    private ?User $user = null;

    protected function tearDown(): void
    {
        StoreCreditTransaction::where('customer_id', $this->customer?->id)->delete();
        $this->customer?->delete();
        $this->user?->delete();

        parent::tearDown();
    }

    /**
     * Two processes each try to debit 60.00 against a starting balance of
     * 100.00 — only one request can be satisfied. The FOR UPDATE lock in
     * StoreCreditService::write() must serialize the two so the loser sees
     * the winner's already-reduced balance and is cleanly rejected, rather
     * than both reading the stale 100.00 and one silently driving the
     * balance negative (a lost-update race).
     */
    public function test_two_concurrent_processes_debiting_the_same_customer_only_one_succeeds(): void
    {
        $this->user = User::factory()->create();
        $this->customer = Customer::create([
            'name' => 'Concurrency Credit Customer',
            'phone' => '0700000098',
            'customer_type' => 'walk_in',
            'status' => 'active',
        ]);

        // store_credit_balance is trigger-synced, not mass-assignable — the
        // only legitimate way to seed a starting balance is through the
        // ledger itself, same as production code would.
        app(StoreCreditService::class)->credit($this->customer, '100.00', 'manual_adjustment', $this->user);

        $artisan = base_path('artisan');
        $php = PHP_BINARY;

        $processA = new Process([$php, $artisan, 'app:debit-store-credit', (string) $this->customer->id, '60.00', '--user='.$this->user->id], base_path());
        $processB = new Process([$php, $artisan, 'app:debit-store-credit', (string) $this->customer->id, '60.00', '--user='.$this->user->id], base_path());

        $processA->start();
        $processB->start();

        while ($processA->isRunning() || $processB->isRunning()) {
            usleep(20_000);
        }

        $outputs = [$processA->getOutput().$processA->getErrorOutput(), $processB->getOutput().$processB->getErrorOutput()];
        $successes = array_filter($outputs, fn ($o) => str_contains($o, 'DEBIT_SUCCESS:'));
        $rejections = array_filter($outputs, fn ($o) => str_contains($o, 'REJECTED_INSUFFICIENT_BALANCE'));

        $this->assertCount(1, $successes, 'Expected exactly one debit to succeed. Outputs: '.implode(' | ', $outputs));
        $this->assertCount(1, $rejections, 'Expected exactly one debit to be cleanly rejected. Outputs: '.implode(' | ', $outputs));

        $this->assertSame('40.00', (string) $this->customer->fresh()->store_credit_balance);
        // The seeded initial credit plus exactly one successful debit — not two.
        $this->assertSame(2, StoreCreditTransaction::where('customer_id', $this->customer->id)->count());
    }
}
