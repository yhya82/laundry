<?php

namespace App\Console\Commands;

use App\Services\CollectionService;
use Illuminate\Console\Command;

class GenerateDueCollections extends Command
{
    protected $signature = 'app:generate-due-collections';

    protected $description = 'Create a collection for every active subscription whose next_collection_date has arrived, and advance that date.';

    public function handle(CollectionService $service): int
    {
        $count = $service->generateDueCollections();

        $this->components->info("Generated {$count} collection(s).");

        return self::SUCCESS;
    }
}
