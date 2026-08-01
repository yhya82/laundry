<?php

namespace Tests\Feature\Damage;

use App\Livewire\Orders\Show as OrderShow;
use App\Models\ClothingType;
use App\Models\Customer;
use App\Models\DamageType;
use App\Models\Package;
use App\Models\Role;
use App\Models\User;
use App\Services\LaundryOrderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class OrderShowDamageTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Customer $customer;

    private DamageType $damageType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cashier = User::factory()->create();
        $this->cashier->roles()->attach(Role::where('slug', 'cashier')->first()->id, ['is_primary' => true]);

        $this->customer = Customer::create(['name' => 'Order Damage Customer', 'phone' => '0700000070', 'customer_type' => 'walk_in', 'status' => 'active']);
        $this->damageType = DamageType::create(['name' => 'Stained', 'status' => 'active']);
    }

    public function test_a_cashier_can_file_a_damage_report_with_evidence_from_the_order_page(): void
    {
        Storage::fake('public');

        $package = Package::create(['name' => 'Pkg', 'price' => 20, 'maximum_clothes' => 5, 'priority' => 'normal', 'status' => 'active']);
        $shirt = ClothingType::create(['name' => 'Shirt', 'status' => 'active']);

        $order = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'cart' => [['package_id' => $package->id, 'items' => [['clothing_type_id' => $shirt->id, 'quantity' => 1]]]],
        ], $this->cashier);

        $itemId = $order->fresh(['packages.items'])->packages->first()->items->first()->id;

        Livewire::actingAs($this->cashier)
            ->test(OrderShow::class, ['order' => $order])
            ->call('openDamageDrawer')
            ->set('damageDescription', 'Shirt returned with a stain')
            ->set('pendingItemId', $itemId)
            ->call('addDamageItem')
            ->set('damageItems.0.damage_type_id', $this->damageType->id)
            ->set('damageItems.0.severity', 'medium')
            ->set('damageEvidence', [UploadedFile::fake()->image('evidence.jpg')])
            ->call('reportDamage')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('damage_reports', ['laundry_order_id' => $order->id, 'status' => 'reported']);
        $this->assertDatabaseHas('damage_items', ['laundry_order_item_id' => $itemId, 'damage_type_id' => $this->damageType->id]);
        $this->assertDatabaseCount('damage_evidence', 1);
    }
}
