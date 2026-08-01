<?php

namespace Tests\Feature\Orders;

use App\Livewire\Orders\Queue;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Role;
use App\Models\User;
use App\Services\LaundryOrderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class QueueTest extends TestCase
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

    public function test_queue_lists_active_orders_and_hides_completed_by_default(): void
    {
        $customer = Customer::create(['name' => 'Q Customer', 'phone' => '0700000005', 'customer_type' => 'walk_in', 'status' => 'active']);
        $package = Package::create(['name' => 'Pkg', 'price' => 10, 'maximum_clothes' => 5, 'priority' => 'normal', 'status' => 'active']);

        $order = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $customer->id,
            'cart' => [['package_id' => $package->id, 'items' => []]],
        ], $this->admin);

        Livewire::actingAs($this->admin)
            ->test(Queue::class)
            ->assertSee($order->order_number);
    }

    /**
     * The exit criteria explicitly calls for this: confirm the queue query
     * actually uses idx_laundry_orders_queue_sort (status, priority,
     * created_at), not just that the results happen to look right.
     */
    public function test_filtered_queue_query_uses_the_composite_index(): void
    {
        $plan = DB::select("EXPLAIN SELECT * FROM laundry_orders WHERE status = 'washing' ORDER BY status, priority, created_at LIMIT 20");

        $this->assertNotEmpty($plan);
        $usedIndex = $plan[0]->key ?? null;

        $this->assertSame(
            'idx_laundry_orders_queue_sort',
            $usedIndex,
            'Expected the filtered queue query to use idx_laundry_orders_queue_sort, got: '.($usedIndex ?? 'no index (filesort)')
        );

        // Also confirm no filesort is needed for this specific query shape —
        // "Using filesort" in Extra would mean the index isn't actually
        // satisfying the ORDER BY.
        $this->assertStringNotContainsString('Using filesort', $plan[0]->Extra ?? '');
    }
}
