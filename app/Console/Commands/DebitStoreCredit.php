<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\User;
use App\Services\StoreCreditService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Thin CLI wrapper around StoreCreditService::debit(), exercising the
 * "other" FOR UPDATE boundary (alongside collection conversion —
 * ConvertCollectionToOrder) for real, separate-process lock contention:
 * two genuinely concurrent debits against the same customer's balance
 * must serialize on the row lock rather than lose an update.
 */
class DebitStoreCredit extends Command
{
    protected $signature = 'app:debit-store-credit {customer : Customer ID} {amount : Amount to debit} {--user= : Acting user ID}';

    protected $description = 'Debit store credit from a customer (used operationally and by the Phase 8 concurrency test).';

    public function handle(StoreCreditService $service): int
    {
        $customer = Customer::find((int) $this->argument('customer'));

        if (! $customer) {
            $this->error('CUSTOMER_NOT_FOUND');

            return self::FAILURE;
        }

        $user = $this->option('user') ? User::find((int) $this->option('user')) : User::first();

        if (! $user) {
            $this->error('USER_NOT_FOUND');

            return self::FAILURE;
        }

        try {
            $transaction = $service->debit($customer, $this->argument('amount'), 'manual_adjustment', $user);
        } catch (ValidationException $e) {
            $this->error('REJECTED_INSUFFICIENT_BALANCE: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('DEBIT_SUCCESS: '.$transaction->id.' '.$transaction->balance_after);

        return self::SUCCESS;
    }
}
