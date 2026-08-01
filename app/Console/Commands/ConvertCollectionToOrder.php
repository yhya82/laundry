<?php

namespace App\Console\Commands;

use App\Models\Collection;
use App\Models\User;
use App\Services\CollectionService;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Thin CLI wrapper around CollectionService::convertToOrder(). Exists so
 * Phase 7's concurrent-double-click exit criteria can be exercised for
 * real — two genuinely separate OS processes racing for the same
 * collection's FOR UPDATE lock — rather than simulated within one PHP
 * process, which can't reproduce real row-lock contention.
 */
class ConvertCollectionToOrder extends Command
{
    protected $signature = 'app:convert-collection {collection : Collection ID} {--user= : Acting user ID}';

    protected $description = 'Convert a collection into a laundry order (used operationally and by the Phase 7 concurrency test).';

    public function handle(CollectionService $service): int
    {
        $collection = Collection::find((int) $this->argument('collection'));

        if (! $collection) {
            $this->error('COLLECTION_NOT_FOUND');

            return self::FAILURE;
        }

        $user = $this->option('user') ? User::find((int) $this->option('user')) : User::first();

        if (! $user) {
            $this->error('USER_NOT_FOUND');

            return self::FAILURE;
        }

        try {
            $order = $service->convertToOrder($collection, $user);
        } catch (RuntimeException $e) {
            // A distinct, non-overlapping marker from the success case below —
            // "ALREADY_CONVERTED:" contains "CONVERTED:" as a literal
            // substring, which broke a naive str_contains() check in the
            // concurrency test until this was renamed.
            $this->error('REJECTED_ALREADY_CONVERTED: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('CONVERSION_SUCCESS: '.$order->id.' '.$order->order_number);

        return self::SUCCESS;
    }
}
