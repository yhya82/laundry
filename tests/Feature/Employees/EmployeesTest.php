<?php

namespace Tests\Feature\Employees;

use App\Livewire\Employees\Index as EmployeesIndex;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmployeesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('slug', 'administrator')->first()->id, ['is_primary' => true]);
    }

    public function test_an_employee_can_be_created_with_no_system_login_access(): void
    {
        $department = Department::create(['name' => 'Laundry Processing']);

        Livewire::actingAs($this->admin)
            ->test(EmployeesIndex::class)
            ->call('create')
            ->set('name', 'Amara Okafor')
            ->set('position', 'Washer')
            ->set('department_id', $department->id)
            ->call('save')
            ->assertHasNoErrors();

        $employee = Employee::where('name', 'Amara Okafor')->firstOrFail();

        $this->assertNull($employee->user_id);
        $this->assertSame($department->id, $employee->department_id);
    }

    public function test_an_employee_can_be_linked_to_an_existing_user_account(): void
    {
        $loginUser = User::factory()->create(['name' => 'Desk Staff']);

        Livewire::actingAs($this->admin)
            ->test(EmployeesIndex::class)
            ->call('create')
            ->set('name', 'Desk Staff')
            ->set('user_id', $loginUser->id)
            ->call('save')
            ->assertHasNoErrors();

        $employee = Employee::where('name', 'Desk Staff')->firstOrFail();

        $this->assertSame($loginUser->id, $employee->user_id);
    }

    public function test_a_user_already_linked_to_an_employee_is_not_offered_again(): void
    {
        $linkedUser = User::factory()->create();
        Employee::create(['name' => 'Existing Employee', 'user_id' => $linkedUser->id, 'status' => 'active']);

        $component = Livewire::actingAs($this->admin)
            ->test(EmployeesIndex::class)
            ->call('create');

        $availableIds = $component->instance()->availableUsers()->pluck('id')->all();

        $this->assertNotContains($linkedUser->id, $availableIds);
    }
}
