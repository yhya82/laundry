<?php

namespace Tests\Feature\Customers;

use App\Livewire\Customers\Addresses;
use App\Livewire\Customers\Notes;
use App\Livewire\Customers\Show as CustomerShow;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerShowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('slug', 'administrator')->first()->id, ['is_primary' => true]);

        $this->customer = Customer::create([
            'name' => 'Test Customer', 'phone' => '0700000001', 'customer_type' => 'walk_in', 'status' => 'active',
        ]);
    }

    public function test_all_nine_tabs_render_without_error(): void
    {
        $tabs = ['overview', 'laundry', 'packages', 'collections', 'payments', 'receipts', 'damages', 'notifications', 'history'];

        foreach ($tabs as $tab) {
            Livewire::actingAs($this->admin)
                ->test(CustomerShow::class, ['customer' => $this->customer])
                ->set('tab', $tab)
                ->assertOk();
        }

        $this->assertCount(9, $tabs);
    }

    public function test_editing_customer_phone_to_a_taken_active_number_is_rejected(): void
    {
        Customer::create(['name' => 'Other', 'phone' => '0700000002', 'customer_type' => 'walk_in', 'status' => 'active']);

        Livewire::actingAs($this->admin)
            ->test(CustomerShow::class, ['customer' => $this->customer])
            ->call('editCustomer')
            ->set('phone', '0700000002')
            ->call('save')
            ->assertHasErrors(['phone']);
    }

    public function test_can_add_an_address_and_only_one_stays_default(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Addresses::class, ['customer' => $this->customer])
            ->call('create')
            ->set('street', '12 Market Rd')
            ->set('city', 'Lagos')
            ->set('is_default', true)
            ->call('save')
            ->assertHasNoErrors();

        Livewire::actingAs($this->admin)
            ->test(Addresses::class, ['customer' => $this->customer])
            ->call('create')
            ->set('street', '5 Harbor Ave')
            ->set('city', 'Lagos')
            ->set('is_default', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, $this->customer->addresses()->where('is_default', true)->count());
    }

    public function test_cannot_edit_an_address_belonging_to_a_different_customer(): void
    {
        $otherCustomer = Customer::create(['name' => 'Other', 'phone' => '0700000003', 'customer_type' => 'walk_in', 'status' => 'active']);
        $otherAddress = $otherCustomer->addresses()->create(['street' => 'Not yours']);

        Livewire::actingAs($this->admin)
            ->test(Addresses::class, ['customer' => $this->customer])
            ->call('edit', $otherAddress->id)
            ->assertStatus(403);
    }

    public function test_can_add_a_note_and_it_records_the_author(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Notes::class, ['customer' => $this->customer])
            ->call('create')
            ->set('note_type', 'internal')
            ->set('content', 'Prefers cold water wash.')
            ->call('save')
            ->assertHasNoErrors();

        $note = $this->customer->notes()->firstOrFail();
        $this->assertSame('internal', $note->note_type);
        $this->assertSame($this->admin->id, $note->created_by);
    }
}
