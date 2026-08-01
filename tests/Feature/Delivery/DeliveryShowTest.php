<?php

namespace Tests\Feature\Delivery;

use App\Livewire\Delivery\Show as DeliveryShow;
use App\Models\Customer;
use App\Models\Delivery;
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

class DeliveryShowTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $cashier;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->manager = User::factory()->create();
        $this->manager->roles()->attach(Role::where('slug', 'manager')->first()->id, ['is_primary' => true]);

        $this->cashier = User::factory()->create();
        $this->cashier->roles()->attach(Role::where('slug', 'cashier')->first()->id, ['is_primary' => true]);

        $this->customer = Customer::create(['name' => 'Show Delivery Customer', 'phone' => '0700000120', 'customer_type' => 'walk_in', 'status' => 'active']);
    }

    private function createDelivery(): Delivery
    {
        $package = Package::create(['name' => 'Pkg', 'price' => 20, 'maximum_clothes' => 5, 'priority' => 'normal', 'status' => 'active']);

        $order = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'delivery_type' => 'delivery',
            'cart' => [['package_id' => $package->id, 'items' => []]],
        ], $this->manager);

        return app(DeliveryService::class)->createDelivery($order->fresh(), [
            'address_id' => null, 'address_snapshot' => 'Test Address', 'delivery_fee' => '0', 'scheduled_date' => null, 'delivery_instructions' => null,
        ], $this->manager);
    }

    public function test_a_cashier_cannot_see_management_actions_but_a_manager_can(): void
    {
        $delivery = $this->createDelivery();

        Livewire::actingAs($this->cashier)
            ->test(DeliveryShow::class, ['delivery' => $delivery])
            ->assertDontSee('Assign staff');

        Livewire::actingAs($this->manager)
            ->test(DeliveryShow::class, ['delivery' => $delivery])
            ->assertSee('Assign staff');
    }

    public function test_full_lifecycle_through_the_ui(): void
    {
        $delivery = $this->createDelivery();
        $employee = Employee::create(['name' => 'UI Courier', 'status' => 'active']);

        Livewire::actingAs($this->manager)
            ->test(DeliveryShow::class, ['delivery' => $delivery])
            ->set('assignEmployeeId', $employee->id)
            ->call('assignStaff')
            ->assertSet('delivery.status', 'assigned')
            ->call('markPickedUp')
            ->assertSet('delivery.status', 'picked_up')
            ->call('markOutForDelivery')
            ->assertSet('delivery.status', 'out_for_delivery')
            ->call('markDelivered')
            ->assertSet('delivery.status', 'delivered');
    }

    public function test_failed_then_rescheduled_through_the_ui(): void
    {
        $delivery = $this->createDelivery();
        $employee = Employee::create(['name' => 'UI Courier 2', 'status' => 'active']);

        Livewire::actingAs($this->manager)
            ->test(DeliveryShow::class, ['delivery' => $delivery])
            ->set('assignEmployeeId', $employee->id)
            ->call('assignStaff')
            ->call('markPickedUp')
            ->call('markOutForDelivery')
            ->set('failureReason', 'Nobody home')
            ->call('markFailed')
            ->assertSet('delivery.status', 'failed')
            ->set('rescheduleDate', '2026-09-01')
            ->call('reschedule')
            ->assertSet('delivery.status', 'scheduled');
    }
}
