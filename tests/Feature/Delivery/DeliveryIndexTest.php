<?php

namespace Tests\Feature\Delivery;

use App\Livewire\Delivery\Index as DeliveryIndex;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Package;
use App\Models\Role;
use App\Models\User;
use App\Services\DeliveryService;
use App\Services\LaundryOrderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DeliveryIndexTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Customer $customer;

    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('slug', 'administrator')->first()->id, ['is_primary' => true]);

        $this->customer = Customer::create(['name' => 'Index Delivery Customer', 'phone' => '0700000130', 'customer_type' => 'walk_in', 'status' => 'active']);
        $this->package = Package::create(['name' => 'Pkg', 'price' => 20, 'maximum_clothes' => 5, 'priority' => 'normal', 'status' => 'active']);
    }

    private function createDeliveryFor(?Employee $employee = null, string $addressLabel = 'Addr'): void
    {
        $order = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'delivery_type' => 'delivery',
            'cart' => [['package_id' => $this->package->id, 'items' => []]],
        ], $this->admin);

        $delivery = app(DeliveryService::class)->createDelivery($order->fresh(), [
            'address_id' => null, 'address_snapshot' => $addressLabel, 'delivery_fee' => '0', 'scheduled_date' => null, 'delivery_instructions' => null,
        ], $this->admin);

        if ($employee) {
            app(DeliveryService::class)->assignStaff($delivery, $employee->id, $this->admin);
        }
    }

    public function test_admin_sees_all_deliveries_by_default(): void
    {
        $this->createDeliveryFor(null, 'Unassigned Address');

        Livewire::actingAs($this->admin)
            ->test(DeliveryIndex::class)
            ->assertSet('myDeliveriesOnly', false)
            ->assertSee('Unassigned Address');
    }

    public function test_delivery_staff_defaults_to_their_own_assignments(): void
    {
        $deliveryUser = User::factory()->create();
        $deliveryUser->roles()->attach(Role::where('slug', 'delivery-staff')->first()->id, ['is_primary' => true]);
        $employee = Employee::create(['user_id' => $deliveryUser->id, 'name' => 'My Courier', 'status' => 'active']);

        $otherEmployee = Employee::create(['name' => 'Other Courier', 'status' => 'active']);

        $this->createDeliveryFor($employee, 'My Delivery Address');
        $this->createDeliveryFor($otherEmployee, 'Other Delivery Address');

        Livewire::actingAs($deliveryUser)
            ->test(DeliveryIndex::class)
            ->assertSet('myDeliveriesOnly', true)
            ->assertSee('My Delivery Address')
            ->assertDontSee('Other Delivery Address');
    }
}
