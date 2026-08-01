<?php

namespace Tests\Feature\Orders;

use App\Livewire\Orders\Terminal;
use App\Models\ClothingType;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TerminalTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Customer $customer;

    private Package $package;

    private ClothingType $shirt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cashier = User::factory()->create();
        $this->cashier->roles()->attach(Role::where('slug', 'cashier')->first()->id, ['is_primary' => true]);

        $this->customer = Customer::create(['name' => 'Terminal Customer', 'phone' => '0700000009', 'customer_type' => 'walk_in', 'status' => 'active']);
        $this->package = Package::create(['name' => 'Quick Wash', 'price' => 15, 'maximum_clothes' => 3, 'priority' => 'normal', 'status' => 'active']);
        $this->shirt = ClothingType::create(['name' => 'Shirt', 'status' => 'active']);
    }

    public function test_clothes_cannot_be_added_before_a_package_is_in_the_cart(): void
    {
        $component = Livewire::actingAs($this->cashier)->test(Terminal::class);

        // No package added yet — activeCartLine is null, so the clothes
        // picker has nothing to attach to. addClothingItem should no-op.
        $component->call('addClothingItem', $this->shirt->id);

        $this->assertSame([], $component->get('cart'));
    }

    public function test_adding_a_package_makes_it_the_active_cart_line_for_clothes(): void
    {
        $component = Livewire::actingAs($this->cashier)->test(Terminal::class);

        $component->call('addPackage', $this->package->id);
        $this->assertCount(1, $component->get('cart'));
        $this->assertSame(0, $component->get('activeCartLine'));

        $component->call('addClothingItem', $this->shirt->id);
        $this->assertSame(1, $component->get('cart')[0]['items'][0]['quantity']);
    }

    public function test_adding_beyond_maximum_clothes_is_blocked_in_the_ui_before_submission(): void
    {
        $component = Livewire::actingAs($this->cashier)->test(Terminal::class)
            ->call('addPackage', $this->package->id);

        // maximum_clothes is 3 for this package.
        $component->call('addClothingItem', $this->shirt->id);
        $component->call('addClothingItem', $this->shirt->id);
        $component->call('addClothingItem', $this->shirt->id);
        $this->assertSame(3, $component->get('cart')[0]['items'][0]['quantity']);

        $component->call('addClothingItem', $this->shirt->id);
        $this->assertSame(3, $component->get('cart')[0]['items'][0]['quantity']); // unchanged
        $component->assertHasErrors(['cart']);
    }

    public function test_completing_an_order_without_a_customer_is_rejected(): void
    {
        Livewire::actingAs($this->cashier)->test(Terminal::class)
            ->call('addPackage', $this->package->id)
            ->call('completeOrder')
            ->assertHasErrors(['customer']);

        $this->assertDatabaseCount('laundry_orders', 0);
    }

    public function test_completing_a_full_order_redirects_to_the_order_page(): void
    {
        Livewire::actingAs($this->cashier)->test(Terminal::class)
            ->call('addPackage', $this->package->id)
            ->call('addClothingItem', $this->shirt->id)
            ->call('selectCustomer', $this->customer->id)
            ->set('payNow', false)
            ->call('completeOrder')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseCount('laundry_orders', 1);
        $this->assertDatabaseHas('laundry_orders', ['customer_id' => $this->customer->id, 'status' => 'waiting_queue']);
    }
}
