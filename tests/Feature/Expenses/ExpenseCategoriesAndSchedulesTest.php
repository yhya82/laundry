<?php

namespace Tests\Feature\Expenses;

use App\Livewire\Expenses\Categories;
use App\Livewire\Expenses\Schedules;
use App\Models\ExpenseCategory;
use App\Models\ExpenseSchedule;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExpenseCategoriesAndSchedulesTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->manager = User::factory()->create();
        $this->manager->roles()->attach(Role::where('slug', 'manager')->first()->id, ['is_primary' => true]);
    }

    public function test_can_create_and_edit_a_category(): void
    {
        Livewire::actingAs($this->manager)
            ->test(Categories::class)
            ->call('create')
            ->set('name', 'Cleaning Supplies')
            ->set('description', 'Detergents and chemicals.')
            ->call('save')
            ->assertHasNoErrors();

        $category = ExpenseCategory::where('name', 'Cleaning Supplies')->firstOrFail();

        Livewire::actingAs($this->manager)
            ->test(Categories::class)
            ->call('edit', $category->id)
            ->set('name', 'Cleaning Supplies Updated')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Cleaning Supplies Updated', $category->fresh()->name);
    }

    public function test_duplicate_category_name_is_rejected(): void
    {
        ExpenseCategory::create(['name' => 'Rent']);

        Livewire::actingAs($this->manager)
            ->test(Categories::class)
            ->call('create')
            ->set('name', 'Rent')
            ->call('save')
            ->assertHasErrors(['name']);
    }

    public function test_can_create_a_schedule_and_pause_it(): void
    {
        $category = ExpenseCategory::create(['name' => 'Rent']);

        Livewire::actingAs($this->manager)
            ->test(Schedules::class)
            ->call('create')
            ->set('title', 'Monthly rent')
            ->set('category_id', $category->id)
            ->set('amount', '500.00')
            ->set('frequency', 'monthly')
            ->set('next_run_date', now()->addMonth()->toDateString())
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('expense_schedules', ['title' => 'Monthly rent', 'status' => 'active', 'created_by' => $this->manager->id]);

        $schedule = ExpenseSchedule::where('title', 'Monthly rent')->firstOrFail();

        Livewire::actingAs($this->manager)
            ->test(Schedules::class)
            ->call('togglePause', $schedule->id);

        $this->assertSame('paused', $schedule->fresh()->status);
    }
}
