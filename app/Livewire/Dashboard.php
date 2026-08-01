<?php

namespace App\Livewire;

use App\Models\Collection;
use App\Models\DamageReport;
use App\Models\Delivery;
use App\Models\Expense;
use App\Models\LaundryOrder;
use App\Services\ReportingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Two distinct data sources on purpose. The "right now" cards are live
 * queries against the operational tables — a queue count or a pending-
 * approval count that's stale by even a few minutes is actively
 * misleading to the staff acting on it. The trend section is sourced
 * from daily_statistics (Phase 13's scheduled aggregation job), per the
 * plan's explicit "scheduled, not live-computed" architecture for
 * historical/analytical numbers — computing a 7-day revenue trend by
 * scanning raw payments on every dashboard load doesn't scale and isn't
 * what that job exists to avoid.
 */
class Dashboard extends Component
{
    #[Computed]
    public function operationalCards(): array
    {
        $user = Auth::user();
        $cards = [];

        if ($user->hasPermission('orders.view')) {
            $cards[] = [
                'label' => 'Orders in queue',
                'value' => LaundryOrder::whereNotIn('status', ['completed', 'cancelled'])->count(),
                'href' => route('orders.index'),
            ];
        }

        if ($user->hasPermission('damage.approve')) {
            $cards[] = [
                'label' => 'Damage reports awaiting decision',
                'value' => DamageReport::whereIn('status', ['reported', 'under_review'])->count(),
                'href' => route('damage.index'),
            ];
        }

        if ($user->hasPermission('expenses.approve')) {
            $cards[] = [
                'label' => 'Expenses awaiting approval',
                'value' => Expense::where('status', 'pending')->count(),
                'href' => route('expenses.index'),
            ];
        }

        if ($user->hasPermission('deliveries.view')) {
            $employeeId = $user->employee?->id;
            $query = Delivery::whereNotIn('status', ['delivered', 'failed', 'cancelled']);

            $cards[] = [
                'label' => $employeeId ? 'My active deliveries' : 'Active deliveries',
                'value' => $employeeId ? $query->where('assigned_staff_id', $employeeId)->count() : $query->count(),
                'href' => route('deliveries.index'),
            ];
        }

        if ($user->hasPermission('collections.manage')) {
            $cards[] = [
                'label' => 'Collections due this week',
                'value' => Collection::where('status', 'scheduled')
                    ->whereBetween('scheduled_date', [Carbon::today(), Carbon::today()->addDays(7)])
                    ->count(),
                'href' => route('collections.index'),
            ];
        }

        return $cards;
    }

    #[Computed]
    public function canViewFinancials(): bool
    {
        return Auth::user()->hasAnyPermission(['reports.view', 'financial.reports.view']);
    }

    #[Computed]
    public function yesterdayMetrics(): array
    {
        if (! $this->canViewFinancials) {
            return [];
        }

        return app(ReportingService::class)->metricsForDate(Carbon::yesterday());
    }

    #[Computed]
    public function revenueTrend(): array
    {
        if (! $this->canViewFinancials) {
            return [];
        }

        return app(ReportingService::class)
            ->trend(ReportingService::METRIC_REVENUE_COLLECTED, Carbon::today()->subDays(7), Carbon::yesterday())
            ->all();
    }

    public function render()
    {
        return view('livewire.dashboard.index')->title('Dashboard');
    }
}
