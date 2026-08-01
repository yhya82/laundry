<?php

namespace Tests\Feature\Payments;

use App\Models\Customer;
use App\Models\Package;
use App\Models\Role;
use App\Models\User;
use App\Services\LaundryOrderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_generated_receipt_renders_as_a_pdf(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('slug', 'administrator')->first()->id, ['is_primary' => true]);

        $customer = Customer::create(['name' => 'PDF Customer', 'phone' => '0700000050', 'customer_type' => 'walk_in', 'status' => 'active']);
        $package = Package::create(['name' => 'Pkg', 'price' => 20, 'maximum_clothes' => 5, 'priority' => 'normal', 'status' => 'active']);

        $order = app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $customer->id,
            'cart' => [['package_id' => $package->id, 'items' => []]],
            'payment' => ['amount' => '20.00', 'payment_method' => 'cash', 'reference' => null],
        ], $admin);

        $response = $this->actingAs($admin)->get(route('receipts.pdf', $order->fresh()->receipt));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }
}
