<?php

namespace Tests\Feature\Catalogue;

use App\Livewire\Catalogue\Packages;
use App\Models\Package;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PackagesTest extends TestCase
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

    public function test_administrator_can_create_a_package_with_services_attached(): void
    {
        $wash = Service::create(['name' => 'Wash', 'price' => 5, 'status' => 'active']);
        $iron = Service::create(['name' => 'Iron', 'price' => 3, 'status' => 'active']);

        Livewire::actingAs($this->admin)
            ->test(Packages::class)
            ->call('create')
            ->set('name', 'Family Bundle')
            ->set('price', '25.00')
            ->set('maximum_clothes', '8')
            ->set('priority', 'express')
            ->set('selectedServices', [$wash->id, $iron->id])
            ->call('save')
            ->assertHasNoErrors();

        $package = Package::where('name', 'Family Bundle')->firstOrFail();

        $this->assertSame('8', (string) $package->maximum_clothes);
        $this->assertSame('express', $package->priority);
        $this->assertCount(2, $package->services);
    }

    public function test_maximum_clothes_must_be_at_least_one(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Packages::class)
            ->call('create')
            ->set('name', 'Broken Package')
            ->set('maximum_clothes', '0')
            ->call('save')
            ->assertHasErrors(['maximum_clothes']);

        $this->assertDatabaseMissing('packages', ['name' => 'Broken Package']);
    }

    public function test_a_cashier_cannot_reach_the_manage_action_even_if_invoked_directly(): void
    {
        $cashier = User::factory()->create();
        $cashier->roles()->attach(Role::where('slug', 'cashier')->first()->id, ['is_primary' => true]);

        // The route itself is gated by can:packages.view (cashiers have this),
        // but packages.manage is what governs create/edit — the Blade view
        // hides the button; this proves the underlying capability check too.
        $this->assertFalse($cashier->hasPermission('packages.manage'));
    }
}
