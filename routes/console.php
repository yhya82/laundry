<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Collection generation is new application logic, not something the SQL
// layer does — see IMPLEMENTATION_PLAN.md Phase 7. Runs early so
// same-day-scheduled collections are visible before the day's operations start.
Schedule::command('app:generate-due-collections')->dailyAt('05:00');
