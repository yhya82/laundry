<?php

namespace Tests\Feature\Customers;

use App\Livewire\Customers\Index as CustomersIndex;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerIndexTest extends TestCase
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

    public function test_search_matches_by_name_or_phone(): void
    {
        Customer::create(['name' => 'Amina Yusuf', 'phone' => '0711000001', 'customer_type' => 'walk_in', 'status' => 'active']);
        Customer::create(['name' => 'Bello Musa', 'phone' => '0722000002', 'customer_type' => 'walk_in', 'status' => 'active']);

        Livewire::actingAs($this->admin)
            ->test(CustomersIndex::class)
            ->set('search', 'Amina')
            ->assertSee('Amina Yusuf')
            ->assertDontSee('Bello Musa');

        Livewire::actingAs($this->admin)
            ->test(CustomersIndex::class)
            ->set('search', '0722')
            ->assertSee('Bello Musa')
            ->assertDontSee('Amina Yusuf');
    }

    public function test_type_and_status_filters_narrow_the_list(): void
    {
        Customer::create(['name' => 'Walk-in Customer', 'phone' => '0700000010', 'customer_type' => 'walk_in', 'status' => 'active']);
        Customer::create(['name' => 'Sub Customer', 'phone' => '0700000011', 'customer_type' => 'subscription', 'status' => 'inactive']);

        Livewire::actingAs($this->admin)
            ->test(CustomersIndex::class)
            ->set('typeFilter', 'subscription')
            ->assertSee('Sub Customer')
            ->assertDontSee('Walk-in Customer');

        Livewire::actingAs($this->admin)
            ->test(CustomersIndex::class)
            ->set('statusFilter', 'inactive')
            ->assertSee('Sub Customer')
            ->assertDontSee('Walk-in Customer');
    }

    public function test_sorting_by_name_toggles_direction_and_reorders_results(): void
    {
        Customer::create(['name' => 'Zainab', 'phone' => '0700000020', 'customer_type' => 'walk_in', 'status' => 'active']);
        Customer::create(['name' => 'Abdul', 'phone' => '0700000021', 'customer_type' => 'walk_in', 'status' => 'active']);

        $component = Livewire::actingAs($this->admin)->test(CustomersIndex::class);

        // Default sort is name/asc — Abdul before Zainab.
        $this->assertSame(['Abdul', 'Zainab'], $component->viewData('customers')->pluck('name')->all());

        // Clicking the already-active column flips direction, not resets it.
        $component->call('sort', 'name');
        $this->assertSame('desc', $component->get('sortDirection'));
        $this->assertSame(['Zainab', 'Abdul'], $component->viewData('customers')->pluck('name')->all());
    }

    public function test_pagination_links_render_with_more_than_a_page_of_customers(): void
    {
        Customer::factory()->count(20)->create();

        Livewire::actingAs($this->admin)
            ->test(CustomersIndex::class)
            ->assertViewHas('customers', fn ($customers) => $customers->hasMorePages());
    }

    public function test_creating_a_customer_with_a_duplicate_active_phone_is_rejected(): void
    {
        Customer::create(['name' => 'Existing', 'phone' => '0700000099', 'customer_type' => 'walk_in', 'status' => 'active']);

        Livewire::actingAs($this->admin)
            ->test(CustomersIndex::class)
            ->call('create')
            ->set('name', 'New Person')
            ->set('phone', '0700000099')
            ->call('save')
            ->assertHasErrors(['phone']);

        $this->assertDatabaseCount('customers', 1);
    }

    public function test_a_soft_deleted_customers_phone_can_be_reused(): void
    {
        $old = Customer::create(['name' => 'Old Customer', 'phone' => '0700000077', 'customer_type' => 'walk_in', 'status' => 'active']);
        $old->delete();

        Livewire::actingAs($this->admin)
            ->test(CustomersIndex::class)
            ->call('create')
            ->set('name', 'New Owner')
            ->set('phone', '0700000077')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('customers', ['name' => 'New Owner', 'phone' => '0700000077', 'deleted_at' => null]);
    }
}
