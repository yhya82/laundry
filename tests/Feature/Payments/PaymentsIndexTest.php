<?php

namespace Tests\Feature\Payments;

use App\Livewire\Payments\Index as PaymentsIndex;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Role;
use App\Models\User;
use App\Services\LaundryOrderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentsIndexTest extends TestCase
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

    public function test_lists_payments_and_filters_by_status(): void
    {
        $customer = Customer::create(['name' => 'Index Customer', 'phone' => '0700000040', 'customer_type' => 'walk_in', 'status' => 'active']);
        $package = Package::create(['name' => 'Pkg', 'price' => 20, 'maximum_clothes' => 5, 'priority' => 'normal', 'status' => 'active']);

        $paid = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $customer->id,
            'cart' => [['package_id' => $package->id, 'items' => []]],
            'payment' => ['amount' => '20.00', 'payment_method' => 'cash', 'reference' => null],
        ], $this->admin);

        $partial = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $customer->id,
            'cart' => [['package_id' => $package->id, 'items' => []]],
            'payment' => ['amount' => '5.00', 'payment_method' => 'card', 'reference' => null],
        ], $this->admin);

        Livewire::actingAs($this->admin)
            ->test(PaymentsIndex::class)
            ->assertSee($paid->fresh()->payments->first()->payment_number)
            ->assertSee($partial->fresh()->payments->first()->payment_number)
            ->set('statusFilter', 'paid')
            ->assertSee($paid->fresh()->payments->first()->payment_number)
            ->assertDontSee($partial->fresh()->payments->first()->payment_number);
    }

    public function test_search_matches_customer_name(): void
    {
        $customer = Customer::create(['name' => 'Findable Person', 'phone' => '0700000041', 'customer_type' => 'walk_in', 'status' => 'active']);
        $other = Customer::create(['name' => 'Someone Else', 'phone' => '0700000042', 'customer_type' => 'walk_in', 'status' => 'active']);
        $package = Package::create(['name' => 'Pkg', 'price' => 20, 'maximum_clothes' => 5, 'priority' => 'normal', 'status' => 'active']);

        $target = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $customer->id,
            'cart' => [['package_id' => $package->id, 'items' => []]],
            'payment' => ['amount' => '20.00', 'payment_method' => 'cash', 'reference' => null],
        ], $this->admin);

        $decoy = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $other->id,
            'cart' => [['package_id' => $package->id, 'items' => []]],
            'payment' => ['amount' => '20.00', 'payment_method' => 'cash', 'reference' => null],
        ], $this->admin);

        Livewire::actingAs($this->admin)
            ->test(PaymentsIndex::class)
            ->set('search', 'Findable')
            ->assertSee($target->fresh()->payments->first()->payment_number)
            ->assertDontSee($decoy->fresh()->payments->first()->payment_number);
    }
}
