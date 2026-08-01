<?php

namespace Tests\Feature\Catalogue;

use App\Livewire\Catalogue\ClothingTypes;
use App\Models\ClothingType;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClothingTypesTest extends TestCase
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

    public function test_can_create_and_then_edit_a_clothing_type(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ClothingTypes::class)
            ->call('create')
            ->set('name', 'Bedsheet')
            ->call('save')
            ->assertHasNoErrors();

        $type = ClothingType::where('name', 'Bedsheet')->firstOrFail();

        Livewire::actingAs($this->admin)
            ->test(ClothingTypes::class)
            ->call('edit', $type->id)
            ->set('name', 'Bedsheet (King)')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Bedsheet (King)', $type->fresh()->name);
    }

    public function test_duplicate_name_is_rejected(): void
    {
        ClothingType::create(['name' => 'Shirt', 'status' => 'active']);

        Livewire::actingAs($this->admin)
            ->test(ClothingTypes::class)
            ->call('create')
            ->set('name', 'Shirt')
            ->call('save')
            ->assertHasErrors(['name']);
    }

    public function test_toggle_status_flips_active_inactive(): void
    {
        $type = ClothingType::create(['name' => 'Towel', 'status' => 'active']);

        Livewire::actingAs($this->admin)
            ->test(ClothingTypes::class)
            ->call('toggleStatus', $type->id);

        $this->assertSame('inactive', $type->fresh()->status);
    }
}
