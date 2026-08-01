<?php

namespace App\Console\Commands;

use App\Services\ReportingService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * The scheduled aggregation job IMPLEMENTATION_PLAN.md Phase 13 flags as a
 * real gap — nothing in Phase 2's migrations creates it, only the
 * daily_statistics table it writes to. Defaults to yesterday, since a day
 * scheduled to run overnight should aggregate the day that just finished,
 * not the one still in progress — matching the "scheduled, not
 * live-computed" architecture the plan calls for.
 */
class AggregateDailyStatistics extends Command
{
    protected $signature = 'app:aggregate-daily-statistics {date? : Date to aggregate (Y-m-d), defaults to yesterday}';

    protected $description = 'Compute and store daily_statistics rows for a single day.';

    public function handle(ReportingService $service): int
    {
        $date = $this->argument('date') ? Carbon::parse($this->argument('date')) : now()->subDay();

        $service->aggregateForDate($date);

        $this->components->info("Aggregated daily statistics for {$date->toDateString()}.");

        return self::SUCCESS;
    }
}
