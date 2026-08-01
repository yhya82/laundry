<?php

namespace Tests\Feature\Reporting;

use App\Livewire\Dashboard;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Package;
use App\Models\Role;
use App\Models\User;
use App\Services\LaundryOrderService;
use App\Services\ReportingService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_a_manager_sees_operational_cards_and_financial_trend(): void
    {
        $manager = User::factory()->create();
        $manager->roles()->attach(Role::where('slug', 'manager')->first()->id, ['is_primary' => true]);

        $customer = Customer::create(['name' => 'Dash Customer', 'phone' => '0700000500', 'customer_type' => 'walk_in', 'status' => 'active']);
        $package = Package::create(['name' => 'Dash Pkg', 'price' => 20, 'maximum_clothes' => 5, 'priority' => 'normal', 'status' => 'active']);
        app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $customer->id,
            'cart' => [['package_id' => $package->id, 'items' => []]],
            'payment' => ['amount' => '20.00', 'payment_method' => 'cash', 'reference' => null],
        ], $manager);

        app(ReportingService::class)->aggregateForDate(Carbon::yesterday());

        Livewire::actingAs($manager)
            ->test(Dashboard::class)
            ->assertSee('Orders in queue')
            ->assertSee('Expenses awaiting approval')
            ->assertSee('Revenue, last 7 days');
    }

    public function test_a_cashier_sees_no_financial_section(): void
    {
        $cashier = User::factory()->create();
        $cashier->roles()->attach(Role::where('slug', 'cashier')->first()->id, ['is_primary' => true]);

        Livewire::actingAs($cashier)
            ->test(Dashboard::class)
            ->assertDontSee('Revenue, last 7 days')
            ->assertDontSee('Expenses awaiting approval'); // requires expenses.approve, cashier lacks it
    }

    public function test_a_delivery_staff_user_sees_their_own_delivery_count_label(): void
    {
        $deliveryUser = User::factory()->create();
        $deliveryUser->roles()->attach(Role::where('slug', 'delivery-staff')->first()->id, ['is_primary' => true]);
        Employee::create(['user_id' => $deliveryUser->id, 'name' => 'Dash Courier', 'status' => 'active']);

        Livewire::actingAs($deliveryUser)
            ->test(Dashboard::class)
            ->assertSee('My active deliveries');
    }
}
