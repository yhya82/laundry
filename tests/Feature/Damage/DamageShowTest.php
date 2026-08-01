<?php

namespace Tests\Feature\Damage;

use App\Livewire\Damage\Show as DamageShow;
use App\Models\ClothingType;
use App\Models\Customer;
use App\Models\DamageReport;
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

class DamageShowTest extends TestCase
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

        $this->customer = Customer::create(['name' => 'Show Damage Customer', 'phone' => '0700000080', 'customer_type' => 'walk_in', 'status' => 'active']);
    }

    private function createReport(): DamageReport
    {
        $package = Package::create(['name' => 'Pkg', 'price' => 20, 'maximum_clothes' => 5, 'priority' => 'normal', 'status' => 'active']);
        $shirt = ClothingType::create(['name' => 'Shirt', 'status' => 'active']);
        $damageType = DamageType::create(['name' => 'Stained', 'status' => 'active']);

        $order = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'cart' => [['package_id' => $package->id, 'items' => [['clothing_type_id' => $shirt->id, 'quantity' => 1]]]],
        ], $this->cashier);

        $itemId = $order->fresh(['packages.items'])->packages->first()->items->first()->id;

        return app(DamageService::class)->createReport($order->fresh(), 'Stained shirt', [
            ['laundry_order_item_id' => $itemId, 'damage_type_id' => $damageType->id, 'severity' => 'medium', 'description' => null],
        ], $this->cashier);
    }

    public function test_a_cashier_cannot_see_resolution_actions_but_a_manager_can(): void
    {
        $report = $this->createReport();

        Livewire::actingAs($this->cashier)
            ->test(DamageShow::class, ['report' => $report])
            ->assertDontSee('Set resolution');

        Livewire::actingAs($this->manager)
            ->test(DamageShow::class, ['report' => $report])
            ->assertSee('Set resolution');
    }

    public function test_a_manager_can_approve_and_resolve_with_store_credit_compensation(): void
    {
        $report = $this->createReport();

        Livewire::actingAs($this->manager)
            ->test(DamageShow::class, ['report' => $report])
            ->call('openResolveDrawer')
            ->set('resolutionType', 'store_credit')
            ->set('cashAmount', '0.00')
            ->set('creditAmount', '10.00')
            ->call('approve')
            ->assertHasNoErrors()
            ->assertSet('report.status', 'approved')
            ->call('resolve')
            ->assertSet('report.status', 'resolved');

        $this->assertSame('10.00', (string) $this->customer->fresh()->store_credit_balance);
        $this->assertDatabaseHas('store_credit_transactions', [
            'customer_id' => $this->customer->id,
            'source_type' => 'damage_compensation',
        ]);
    }

    public function test_a_manager_can_reject_a_report(): void
    {
        $report = $this->createReport();

        Livewire::actingAs($this->manager)
            ->test(DamageShow::class, ['report' => $report])
            ->call('reject')
            ->assertSet('report.status', 'rejected');
    }
}
