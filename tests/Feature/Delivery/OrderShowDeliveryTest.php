<?php

namespace Tests\Feature\Delivery;

use App\Livewire\Orders\Show as OrderShow;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Role;
use App\Models\User;
use App\Services\LaundryOrderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderShowDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Customer $customer;

    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->manager = User::factory()->create();
        $this->manager->roles()->attach(Role::where('slug', 'manager')->first()->id, ['is_primary' => true]);

        $this->customer = Customer::create(['name' => 'Order Delivery Customer', 'phone' => '0700000110', 'customer_type' => 'walk_in', 'status' => 'active']);
        $this->package = Package::create(['name' => 'Pkg', 'price' => 25, 'maximum_clothes' => 5, 'priority' => 'normal', 'status' => 'active']);
    }

    public function test_a_manager_can_schedule_a_delivery_from_the_order_page(): void
    {
        $order = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'delivery_type' => 'delivery',
            'cart' => [['package_id' => $this->package->id, 'items' => []]],
        ], $this->manager);

        Livewire::actingAs($this->manager)
            ->test(OrderShow::class, ['order' => $order])
            ->call('openDeliveryDrawer')
            ->set('deliveryAddressId', null)
            ->set('deliveryAddressSnapshot', '10 Downing St')
            ->set('deliveryFee', '4.50')
            ->call('scheduleDelivery')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('deliveries', ['laundry_order_id' => $order->id, 'address_snapshot' => '10 Downing St']);
        $this->assertSame('29.50', (string) $order->fresh()->total_amount);
    }

    public function test_the_schedule_delivery_button_is_hidden_for_a_pickup_order(): void
    {
        $order = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'delivery_type' => 'pickup',
            'cart' => [['package_id' => $this->package->id, 'items' => []]],
        ], $this->manager);

        Livewire::actingAs($this->manager)
            ->test(OrderShow::class, ['order' => $order])
            ->assertDontSee('Schedule delivery');
    }
}
