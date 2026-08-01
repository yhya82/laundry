<?php

namespace Tests\Feature\Expenses;

use App\Livewire\Expenses\Index as ExpensesIndex;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExpensesIndexTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $cashier;

    private ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingSeeder::class);

        $this->manager = User::factory()->create();
        $this->manager->roles()->attach(Role::where('slug', 'manager')->first()->id, ['is_primary' => true]);

        $this->cashier = User::factory()->create();
        $this->cashier->roles()->attach(Role::where('slug', 'cashier')->first()->id, ['is_primary' => true]);

        $this->category = ExpenseCategory::create(['name' => 'Utilities']);
    }

    public function test_a_manager_can_record_an_expense_under_the_threshold_and_it_is_auto_approved(): void
    {
        Livewire::actingAs($this->manager)
            ->test(ExpensesIndex::class)
            ->call('openCreateDrawer')
            ->set('title', 'Electric bill')
            ->set('category_id', $this->category->id)
            ->set('amount', '75.00')
            ->set('payment_method', 'bank_transfer')
            ->set('expense_date', now()->toDateString())
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('expenses', ['title' => 'Electric bill', 'status' => 'approved']);
    }

    public function test_a_manager_can_approve_a_pending_expense(): void
    {
        $expense = Expense::create([
            'title' => 'Pending expense',
            'category_id' => $this->category->id,
            'amount' => '150.00',
            'payment_method' => 'cash',
            'status' => 'pending',
            'created_by' => $this->manager->id,
            'expense_date' => now()->toDateString(),
        ]);

        Livewire::actingAs($this->manager)
            ->test(ExpensesIndex::class)
            ->call('approve', $expense->id);

        $this->assertSame('approved', $expense->fresh()->status);
    }

    public function test_a_cashier_cannot_see_approve_actions(): void
    {
        $expense = Expense::create([
            'title' => 'Pending expense',
            'category_id' => $this->category->id,
            'amount' => '150.00',
            'payment_method' => 'cash',
            'status' => 'pending',
            'created_by' => $this->manager->id,
            'expense_date' => now()->toDateString(),
        ]);

        Livewire::actingAs($this->cashier)
            ->test(ExpensesIndex::class)
            // Not a bare "Approve" substring check — the status filter's
            // "Approved" option would false-positive-match that.
            ->assertDontSee('>Approve<', false);
    }

    public function test_status_filter_narrows_the_list(): void
    {
        Expense::create([
            'title' => 'Approved one', 'category_id' => $this->category->id, 'amount' => '10.00',
            'payment_method' => 'cash', 'status' => 'approved', 'created_by' => $this->manager->id, 'expense_date' => now()->toDateString(),
        ]);
        Expense::create([
            'title' => 'Rejected one', 'category_id' => $this->category->id, 'amount' => '10.00',
            'payment_method' => 'cash', 'status' => 'rejected', 'created_by' => $this->manager->id, 'expense_date' => now()->toDateString(),
        ]);

        Livewire::actingAs($this->manager)
            ->test(ExpensesIndex::class)
            ->assertSee('Approved one')
            ->assertSee('Rejected one')
            ->set('statusFilter', 'rejected')
            ->assertDontSee('Approved one')
            ->assertSee('Rejected one');
    }
}
