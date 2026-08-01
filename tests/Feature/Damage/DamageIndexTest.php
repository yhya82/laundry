<?php

namespace Tests\Feature\Damage;

use App\Livewire\Damage\Index as DamageIndex;
use App\Models\ClothingType;
use App\Models\Customer;
use App\Models\DamageType;
use App\Models\Package;
use App\Models\Role;
use App\Models\User;
use App\Services\DamageService;
use App\Services\LaundryOrderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DamageIndexTest extends TestCase
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

    public function test_lists_reports_and_filters_by_status(): void
    {
        $customer = Customer::create(['name' => 'Index Damage Customer', 'phone' => '0700000090', 'customer_type' => 'walk_in', 'status' => 'active']);
        $package = Package::create(['name' => 'Pkg', 'price' => 20, 'maximum_clothes' => 5, 'priority' => 'normal', 'status' => 'active']);
        $shirt = ClothingType::create(['name' => 'Shirt', 'status' => 'active']);
        $damageType = DamageType::create(['name' => 'Stained', 'status' => 'active']);

        $order = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $customer->id,
            'cart' => [['package_id' => $package->id, 'items' => [['clothing_type_id' => $shirt->id, 'quantity' => 1]]]],
        ], $this->admin);

        $itemId = $order->fresh(['packages.items'])->packages->first()->items->first()->id;

        $reported = app(DamageService::class)->createReport($order->fresh(), 'Reported report', [
            ['laundry_order_item_id' => $itemId, 'damage_type_id' => $damageType->id, 'severity' => 'low', 'description' => null],
        ], $this->admin);

        $rejected = app(DamageService::class)->createReport($order->fresh(), 'Rejected report', [
            ['laundry_order_item_id' => $itemId, 'damage_type_id' => $damageType->id, 'severity' => 'low', 'description' => null],
        ], $this->admin);
        app(DamageService::class)->reject($rejected, $this->admin);

        Livewire::actingAs($this->admin)
            ->test(DamageIndex::class)
            ->assertSee('Reported report')
            ->assertSee('Rejected report')
            ->set('statusFilter', 'rejected')
            ->assertDontSee('Reported report')
            ->assertSee('Rejected report');
    }
}
