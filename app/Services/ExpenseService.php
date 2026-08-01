<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseSchedule;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Owns expense recording (with the approval-threshold gate) and the
 * pending -> approved/rejected -> paid/cancelled status lifecycle.
 * Unlike Phases 8-10's services, no database trigger backstops any of
 * this — expenses.sql's chk_expenses_status only constrains the *set* of
 * legal status strings, not transitions between them (confirmed: zero
 * triggers touch expenses/expense_schedules/expense_categories) — so
 * every rule here is application-only, with no SQLSTATE 45000 to
 * translate.
 */
class ExpenseService
{
    /**
     * @param  array{title: string, category_id: int, amount: string, payment_method: string, description: ?string, attachment_path: ?string, expense_date: string}  $data
     */
    public function createExpense(array $data, User $actor): Expense
    {
        $needsApproval = $this->exceedsThreshold($data['amount']);
        $canSelfApprove = ! $needsApproval || $actor->hasPermission('expenses.approve');

        return Expense::create([
            ...$data,
            'status' => $canSelfApprove ? 'approved' : 'pending',
            'created_by' => $actor->id,
            'approved_by' => $canSelfApprove ? $actor->id : null,
        ]);
    }

    private function exceedsThreshold(string $amount): bool
    {
        $threshold = (string) (Setting::where('setting_group', 'expenses')
            ->where('setting_key', 'approval_threshold_amount')
            ->value('setting_value') ?? 100);

        return bccomp($amount, $threshold, 2) > 0;
    }

    public function approve(Expense $expense, User $actor): Expense
    {
        $this->assertStatusIn($expense, ['pending'], 'approved');

        $expense->update(['status' => 'approved', 'approved_by' => $actor->id]);

        return $expense->fresh();
    }

    public function reject(Expense $expense, User $actor): Expense
    {
        $this->assertStatusIn($expense, ['pending'], 'rejected');

        $expense->update(['status' => 'rejected', 'approved_by' => $actor->id]);

        return $expense->fresh();
    }

    public function markPaid(Expense $expense): Expense
    {
        $this->assertStatusIn($expense, ['approved'], 'marked paid');

        $expense->update(['status' => 'paid']);

        return $expense->fresh();
    }

    public function cancel(Expense $expense): Expense
    {
        $this->assertStatusIn($expense, ['pending', 'approved'], 'cancelled');

        $expense->update(['status' => 'cancelled']);

        return $expense->fresh();
    }

    /**
     * Days-between-runs per frequency — expense_schedules' own frequency
     * enum ('weekly','monthly','quarterly','yearly'), mirroring
     * CollectionService's FREQUENCY_DAYS/intervalDaysFor shape but with
     * calendar-aware Carbon methods (NoOverflow variants) since these are
     * month/quarter/year cadences, not fixed day counts.
     */
    private function advance(Carbon $date, string $frequency): Carbon
    {
        return match ($frequency) {
            'weekly' => $date->addWeek(),
            'monthly' => $date->addMonthNoOverflow(),
            'quarterly' => $date->addMonthsNoOverflow(3),
            'yearly' => $date->addYearNoOverflow(),
            default => $date->addMonthNoOverflow(),
        };
    }

    /**
     * Creates an `expenses` row for every active schedule whose
     * next_run_date has arrived, then advances that schedule's
     * next_run_date — idempotent per day, same shape as
     * CollectionService::generateDueCollections(). A schedule's own
     * existence represents a standing, already-approved commitment (rent,
     * salaries, etc.), so each generated expense is recorded pre-approved
     * by the schedule's creator, not left pending.
     */
    public function generateDueExpenses(?Carbon $asOf = null): int
    {
        $asOf ??= now();
        $generated = 0;

        ExpenseSchedule::where('status', 'active')
            ->where('next_run_date', '<=', $asOf->toDateString())
            ->each(function (ExpenseSchedule $schedule) use (&$generated) {
                DB::transaction(function () use ($schedule) {
                    Expense::create([
                        'expense_schedule_id' => $schedule->id,
                        'title' => $schedule->title,
                        'category_id' => $schedule->category_id,
                        'amount' => $schedule->amount,
                        'payment_method' => 'bank_transfer',
                        'description' => "Auto-generated from recurring schedule \"{$schedule->title}\".",
                        'status' => 'approved',
                        'created_by' => $schedule->created_by,
                        'approved_by' => $schedule->created_by,
                        'expense_date' => $schedule->next_run_date,
                    ]);

                    $schedule->update([
                        'next_run_date' => $this->advance(Carbon::parse($schedule->next_run_date), $schedule->frequency),
                    ]);
                });

                $generated++;
            });

        return $generated;
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function assertStatusIn(Expense $expense, array $allowed, string $verb): void
    {
        if (! in_array($expense->status, $allowed, true)) {
            throw new RuntimeException("This expense cannot be {$verb} from its current status (\"{$expense->status}\").");
        }
    }
}
