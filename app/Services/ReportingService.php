<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\Customer;
use App\Models\DailyStatistic;
use App\Models\DamageReport;
use App\Models\Delivery;
use App\Models\Expense;
use App\Models\LaundryOrder;
use App\Models\LaundryOrderStageHistory;
use App\Models\Payment;
use App\Models\Refund;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Owns the daily_statistics aggregation job (IMPLEMENTATION_PLAN.md Phase
 * 13's "scheduled, not live-computed" directive) plus the live query
 * helpers the Reports page uses for arbitrary/current-day ranges that a
 * once-nightly pre-aggregation can't cover.
 *
 * daily_statistics is EAV-shaped (stat_date, metric_key, metric_value) —
 * no CHECK constraint or seeded list defines the set of valid metric_key
 * strings anywhere in this schema, so the METRIC_* constants below are
 * this build's own vocabulary, not ported from a spec (same situation
 * Phase 12 found for notifications.type).
 */
class ReportingService
{
    public const METRIC_ORDERS_CREATED = 'orders_created';

    public const METRIC_ORDERS_COMPLETED = 'orders_completed';

    public const METRIC_ORDERS_CANCELLED = 'orders_cancelled';

    public const METRIC_REVENUE_COLLECTED = 'revenue_collected';

    public const METRIC_REFUNDS_ISSUED = 'refunds_issued';

    public const METRIC_NET_REVENUE = 'net_revenue';

    public const METRIC_EXPENSES_RECORDED = 'expenses_recorded';

    public const METRIC_NEW_CUSTOMERS = 'new_customers';

    public const METRIC_DAMAGE_REPORTS_FILED = 'damage_reports_filed';

    public const METRIC_DAMAGE_COMPENSATION_PAID = 'damage_compensation_paid';

    public const METRIC_DELIVERIES_COMPLETED = 'deliveries_completed';

    public const METRIC_COLLECTIONS_SCHEDULED = 'collections_scheduled';

    /**
     * Computes every metric for a single calendar day and upserts each as
     * its own daily_statistics row (branch_id left null — this build has
     * no multi-branch data to scope by yet). Idempotent: reruns for the
     * same date update the existing rows rather than duplicating them,
     * since uq_daily_statistics's NULL branch_id isn't actually enforced
     * as unique by MySQL (NULL <> NULL) — updateOrCreate's own generated
     * WHERE clause is what keeps this safe on rerun, not the DB constraint.
     */
    public function aggregateForDate(CarbonInterface $date): void
    {
        $revenue = (string) Payment::whereIn('payment_status', ['paid', 'partial'])
            ->whereDate('created_at', $date)
            ->sum('amount');

        $refunds = (string) Refund::whereDate('created_at', $date)->sum('amount');

        $metrics = [
            self::METRIC_ORDERS_CREATED => LaundryOrder::whereDate('created_at', $date)->count(),
            self::METRIC_ORDERS_COMPLETED => LaundryOrderStageHistory::where('stage', 'completed')->whereDate('started_at', $date)->count(),
            self::METRIC_ORDERS_CANCELLED => LaundryOrder::where('status', 'cancelled')->whereDate('cancelled_at', $date)->count(),
            self::METRIC_REVENUE_COLLECTED => $revenue,
            self::METRIC_REFUNDS_ISSUED => $refunds,
            self::METRIC_NET_REVENUE => bcsub($revenue, $refunds, 2),
            self::METRIC_EXPENSES_RECORDED => (string) Expense::whereIn('status', ['approved', 'paid'])->whereDate('expense_date', $date)->sum('amount'),
            self::METRIC_NEW_CUSTOMERS => Customer::whereDate('created_at', $date)->count(),
            self::METRIC_DAMAGE_REPORTS_FILED => DamageReport::whereDate('created_at', $date)->count(),
            self::METRIC_DAMAGE_COMPENSATION_PAID => (string) DamageReport::where('status', 'resolved')
                ->whereDate('updated_at', $date)
                ->select(DB::raw('COALESCE(SUM(cash_compensation_amount + store_credit_compensation_amount), 0) as total'))
                ->value('total'),
            self::METRIC_DELIVERIES_COMPLETED => Delivery::where('status', 'delivered')->whereDate('completed_date', $date)->count(),
            self::METRIC_COLLECTIONS_SCHEDULED => Collection::whereDate('created_at', $date)->count(),
        ];

        foreach ($metrics as $key => $value) {
            DailyStatistic::updateOrCreate(
                ['stat_date' => $date->toDateString(), 'metric_key' => $key, 'branch_id' => null],
                ['metric_value' => $value],
            );
        }
    }

    /**
     * @return array<string, string>
     */
    public function metricsForDate(CarbonInterface $date): array
    {
        return DailyStatistic::whereDate('stat_date', $date)
            ->whereNull('branch_id')
            ->pluck('metric_value', 'metric_key')
            ->map(fn ($value) => (string) $value)
            ->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{stat_date: string, value: string}>
     */
    public function trend(string $metricKey, CarbonInterface $from, CarbonInterface $to)
    {
        // Reads idx_daily_statistics_date_key (stat_date, metric_key) —
        // metric_key is the second column, so this is only a fully
        // index-covered lookup when stat_date is also bounded, which it
        // always is here (a trend is always a bounded range, never
        // "all history for this key").
        return DailyStatistic::whereBetween('stat_date', [$from->toDateString(), $to->toDateString()])
            ->where('metric_key', $metricKey)
            ->whereNull('branch_id')
            ->orderBy('stat_date')
            ->get()
            ->map(fn ($row) => ['stat_date' => $row->stat_date->toDateString(), 'value' => (string) $row->metric_value]);
    }

    /**
     * The Reports page's queries, unlike the dashboard trend above, read
     * live from source tables — a report with a range that includes today
     * needs data the nightly job hasn't produced yet, and an arbitrary
     * historical range doesn't map cleanly onto per-day EAV rows once you
     * need row-level detail (which payment, which expense), not just a
     * daily total. idx_payments_created_at / idx_expenses_date_category
     * back these two range scans (confirmed via EXPLAIN — see the
     * Phase 13 verification notes).
     *
     * @return array{rows: \Illuminate\Support\Collection, total_revenue: string, total_refunds: string, net_revenue: string}
     */
    public function revenueReport(CarbonInterface $from, CarbonInterface $to): array
    {
        $payments = Payment::with('customer', 'laundryOrder')
            ->whereIn('payment_status', ['paid', 'partial'])
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->orderBy('created_at')
            ->get();

        $refunds = (string) Refund::whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])->sum('amount');

        $totalRevenue = (string) $payments->sum(fn ($p) => (string) $p->amount);

        return [
            'rows' => $payments,
            'total_revenue' => $totalRevenue,
            'total_refunds' => $refunds,
            'net_revenue' => bcsub($totalRevenue, $refunds, 2),
        ];
    }

    /**
     * @return array{rows: \Illuminate\Support\Collection, by_category: \Illuminate\Support\Collection, total: string}
     */
    public function expensesReport(CarbonInterface $from, CarbonInterface $to): array
    {
        $expenses = Expense::with('category')
            ->whereIn('status', ['approved', 'paid'])
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('expense_date')
            ->get();

        return [
            'rows' => $expenses,
            'by_category' => $expenses->groupBy(fn ($e) => $e->category->name)->map(fn ($group) => (string) $group->sum(fn ($e) => (string) $e->amount)),
            'total' => (string) $expenses->sum(fn ($e) => (string) $e->amount),
        ];
    }

    /**
     * @return array{rows: \Illuminate\Support\Collection, by_status: \Illuminate\Support\Collection}
     */
    public function ordersReport(CarbonInterface $from, CarbonInterface $to): array
    {
        $orders = LaundryOrder::with('customer')
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->orderBy('created_at')
            ->get();

        return [
            'rows' => $orders,
            'by_status' => $orders->groupBy('status')->map->count(),
        ];
    }
}
