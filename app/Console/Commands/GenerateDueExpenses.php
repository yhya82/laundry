<?php

namespace App\Console\Commands;

use App\Services\ExpenseService;
use Illuminate\Console\Command;

class GenerateDueExpenses extends Command
{
    protected $signature = 'app:generate-due-expenses';

    protected $description = 'Create an expense for every active recurring schedule whose next_run_date has arrived, and advance that date.';

    public function handle(ExpenseService $service): int
    {
        $count = $service->generateDueExpenses();

        $this->components->info("Generated {$count} expense(s).");

        return self::SUCCESS;
    }
}
