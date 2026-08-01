<?php

namespace Tests\Feature\Expenses;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseSchedule;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ExpenseService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ExpenseServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $juniorAccountant;

    private ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingSeeder::class); // approval_threshold_amount = 100

        $this->manager = User::factory()->create();
        $this->manager->roles()->attach(Role::where('slug', 'manager')->first()->id, ['is_primary' => true]);

        // No seeded role has expenses.create without expenses.approve — both
        // permissions are only ever granted together (Manager/Admin). This
        // role exists purely to prove the threshold gate itself works.
        $juniorRole = Role::create(['name' => 'Junior Accountant', 'slug' => 'junior-accountant', 'status' => 'active']);
        $juniorRole->permissions()->attach(Permission::where('slug', 'expenses.create')->first()->id);
        $this->juniorAccountant = User::factory()->create();
        $this->juniorAccountant->roles()->attach($juniorRole->id, ['is_primary' => true]);

        $this->category = ExpenseCategory::create(['name' => 'Supplies']);
    }

    private function baseData(string $amount): array
    {
        return [
            'title' => 'Detergent restock',
            'category_id' => $this->category->id,
            'amount' => $amount,
            'payment_method' => 'cash',
            'description' => null,
            'attachment_path' => null,
            'expense_date' => now()->toDateString(),
        ];
    }

    public function test_an_expense_under_the_threshold_is_auto_approved(): void
    {
        $expense = app(ExpenseService::class)->createExpense($this->baseData('50.00'), $this->juniorAccountant);

        $this->assertSame('approved', $expense->status);
        $this->assertSame($this->juniorAccountant->id, $expense->approved_by);
    }

    public function test_an_expense_over_the_threshold_without_approve_permission_is_recorded_pending(): void
    {
        $expense = app(ExpenseService::class)->createExpense($this->baseData('150.00'), $this->juniorAccountant);

        $this->assertSame('pending', $expense->status);
        $this->assertNull($expense->approved_by);
    }

    public function test_an_expense_over_the_threshold_by_a_manager_is_self_approved(): void
    {
        $expense = app(ExpenseService::class)->createExpense($this->baseData('150.00'), $this->manager);

        $this->assertSame('approved', $expense->status);
        $this->assertSame($this->manager->id, $expense->approved_by);
    }

    public function test_approve_and_reject_only_apply_to_pending_expenses(): void
    {
        $pending = app(ExpenseService::class)->createExpense($this->baseData('150.00'), $this->juniorAccountant);
        $service = app(ExpenseService::class);

        $approved = $service->approve($pending, $this->manager);
        $this->assertSame('approved', $approved->status);
        $this->assertSame($this->manager->id, $approved->approved_by);

        $this->expectException(RuntimeException::class);
        $service->approve($approved, $this->manager);
    }

    public function test_rejecting_a_pending_expense(): void
    {
        $pending = app(ExpenseService::class)->createExpense($this->baseData('150.00'), $this->juniorAccountant);

        $rejected = app(ExpenseService::class)->reject($pending, $this->manager);

        $this->assertSame('rejected', $rejected->status);
    }

    public function test_marking_an_approved_expense_paid(): void
    {
        $expense = app(ExpenseService::class)->createExpense($this->baseData('50.00'), $this->manager);

        $paid = app(ExpenseService::class)->markPaid($expense);

        $this->assertSame('paid', $paid->status);
    }

    public function test_marking_a_pending_expense_paid_is_rejected(): void
    {
        $expense = app(ExpenseService::class)->createExpense($this->baseData('150.00'), $this->juniorAccountant);

        $this->expectException(RuntimeException::class);

        app(ExpenseService::class)->markPaid($expense);
    }

    public function test_cancelling_a_pending_or_approved_expense(): void
    {
        $expense = app(ExpenseService::class)->createExpense($this->baseData('50.00'), $this->manager);

        $cancelled = app(ExpenseService::class)->cancel($expense);

        $this->assertSame('cancelled', $cancelled->status);
    }

    public function test_generate_due_expenses_creates_a_linked_row_and_advances_the_schedule(): void
    {
        $schedule = ExpenseSchedule::create([
            'title' => 'Monthly rent',
            'category_id' => $this->category->id,
            'amount' => '500.00',
            'frequency' => 'monthly',
            'next_run_date' => '2026-08-01',
            'status' => 'active',
            'created_by' => $this->manager->id,
        ]);

        $generated = app(ExpenseService::class)->generateDueExpenses(Carbon::parse('2026-08-01'));

        $this->assertSame(1, $generated);
        $this->assertDatabaseHas('expenses', [
            'expense_schedule_id' => $schedule->id,
            'amount' => '500.00',
            'status' => 'approved',
            'expense_date' => '2026-08-01',
        ]);
        $this->assertSame('2026-09-01', $schedule->fresh()->next_run_date->toDateString());
    }

    public function test_generate_due_expenses_is_idempotent_for_a_schedule_not_yet_due(): void
    {
        ExpenseSchedule::create([
            'title' => 'Yearly insurance',
            'category_id' => $this->category->id,
            'amount' => '1200.00',
            'frequency' => 'yearly',
            'next_run_date' => '2026-12-01',
            'status' => 'active',
            'created_by' => $this->manager->id,
        ]);

        $generated = app(ExpenseService::class)->generateDueExpenses(Carbon::parse('2026-08-01'));

        $this->assertSame(0, $generated);
        $this->assertSame(0, Expense::count());
    }

    public function test_a_paused_schedule_does_not_generate(): void
    {
        ExpenseSchedule::create([
            'title' => 'Paused schedule',
            'category_id' => $this->category->id,
            'amount' => '100.00',
            'frequency' => 'weekly',
            'next_run_date' => '2026-08-01',
            'status' => 'paused',
            'created_by' => $this->manager->id,
        ]);

        $generated = app(ExpenseService::class)->generateDueExpenses(Carbon::parse('2026-08-01'));

        $this->assertSame(0, $generated);
    }
}
