<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\StoreCreditTransaction;
use App\Models\User;
use App\Services\Concerns\RetriesOnDeadlock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Every store-credit write goes through here. trg_sct_verify_balance
 * already takes `SELECT store_credit_balance ... FOR UPDATE` on the
 * customer row inside the trigger itself and rejects a debit that would
 * drive the balance negative — this service adds the other half
 * MASTER_SPECIFICATION.md §8 flags as never having been written: the
 * application-layer retry-on-1213 wrapper around that lock, so a real
 * deadlock under concurrent writes gets retried instead of surfacing as a
 * hard failure to whoever happened to lose the race.
 */
class StoreCreditService
{
    use RetriesOnDeadlock;

    public function credit(Customer $customer, string $amount, string $sourceType, User $actor, ?string $sourceTable = null, ?int $sourceId = null, ?string $note = null): StoreCreditTransaction
    {
        return $this->write($customer, 'credit', $amount, $sourceType, $actor, $sourceTable, $sourceId, $note);
    }

    public function debit(Customer $customer, string $amount, string $sourceType, User $actor, ?string $sourceTable = null, ?int $sourceId = null, ?string $note = null): StoreCreditTransaction
    {
        return $this->write($customer, 'debit', $amount, $sourceType, $actor, $sourceTable, $sourceId, $note);
    }

    private function write(Customer $customer, string $direction, string $amount, string $sourceType, User $actor, ?string $sourceTable, ?int $sourceId, ?string $note): StoreCreditTransaction
    {
        return $this->retryOnDeadlock(function () use ($customer, $direction, $amount, $sourceType, $actor, $sourceTable, $sourceId, $note) {
            return DB::transaction(function () use ($customer, $direction, $amount, $sourceType, $actor, $sourceTable, $sourceId, $note) {
                // Re-read under the trigger's own FOR UPDATE lock rather than
                // trusting a balance read earlier in the request — two
                // concurrent debits must serialize on this row, not race.
                $current = Customer::where('id', $customer->id)->lockForUpdate()->value('store_credit_balance');
                $balanceAfter = $direction === 'credit'
                    ? bcadd((string) $current, $amount, 2)
                    : bcsub((string) $current, $amount, 2);

                if ($direction === 'debit' && bccomp($balanceAfter, '0', 2) < 0) {
                    throw ValidationException::withMessages([
                        'amount' => 'This would drive the customer\'s store credit balance below zero.',
                    ]);
                }

                return StoreCreditTransaction::create([
                    'customer_id' => $customer->id,
                    'direction' => $direction,
                    'amount' => $amount,
                    'balance_after' => $balanceAfter,
                    'source_type' => $sourceType,
                    'source_table' => $sourceTable,
                    'source_id' => $sourceId,
                    'reference_note' => $note,
                    'created_by' => $actor->id,
                ]);
            });
        });
    }
}
