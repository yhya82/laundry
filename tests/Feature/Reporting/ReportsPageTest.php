<?php

namespace Tests\Feature\Reporting;

use App\Livewire\Reports\Index as ReportsIndex;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\Package;
use App\Models\ReportExport;
use App\Models\Role;
use App\Models\User;
use App\Services\LaundryOrderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReportsPageTest extends TestCase
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

        $this->customer = Customer::create(['name' => 'Reports Page Customer', 'phone' => '0700000600', 'customer_type' => 'walk_in', 'status' => 'active']);
        $package = Package::create(['name' => 'Reports Pkg', 'price' => 40, 'maximum_clothes' => 5, 'priority' => 'normal', 'status' => 'active']);
        app(LaundryOrderService::class)->createWalkInOrder([
            'customer_id' => $this->customer->id,
            'cart' => [['package_id' => $package->id, 'items' => []]],
            'payment' => ['amount' => '40.00', 'payment_method' => 'cash', 'reference' => null],
        ], $this->manager);
    }

    public function test_a_cashier_cannot_reach_the_reports_page(): void
    {
        $this->actingAs($this->cashier)->get(route('reports.index'))->assertForbidden();
    }

    public function test_a_manager_sees_the_revenue_report_by_default(): void
    {
        Livewire::actingAs($this->manager)
            ->test(ReportsIndex::class)
            ->assertSee('40.00');
    }

    public function test_switching_report_type_shows_expenses(): void
    {
        ExpenseCategory::create(['name' => 'Reports Page Category']);

        Livewire::actingAs($this->manager)
            ->test(ReportsIndex::class)
            ->set('reportType', 'expenses')
            ->assertSee('No expenses in this range');
    }

    public function test_pdf_export_streams_a_pdf_and_logs_a_report_exports_row(): void
    {
        $response = $this->actingAs($this->manager)->get(route('reports.export', ['format' => 'pdf', 'report_type' => 'revenue', 'from' => now()->subDays(7)->toDateString(), 'to' => now()->toDateString()]));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertDatabaseHas('report_exports', ['report_type' => 'revenue', 'export_format' => 'pdf', 'generated_by' => $this->manager->id]);
    }

    public function test_csv_export_streams_csv_and_logs_a_report_exports_row(): void
    {
        $response = $this->actingAs($this->manager)->get(route('reports.export', ['format' => 'csv', 'report_type' => 'revenue', 'from' => now()->subDays(7)->toDateString(), 'to' => now()->toDateString()]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertDatabaseHas('report_exports', ['export_format' => 'csv']);
    }

    public function test_excel_export_streams_xls_and_logs_a_report_exports_row(): void
    {
        $response = $this->actingAs($this->manager)->get(route('reports.export', ['format' => 'excel', 'report_type' => 'orders', 'from' => now()->subDays(7)->toDateString(), 'to' => now()->toDateString()]));

        $response->assertOk();
        $this->assertSame('application/vnd.ms-excel', $response->headers->get('content-type'));
        $this->assertDatabaseHas('report_exports', ['export_format' => 'excel', 'report_type' => 'orders']);
    }

    public function test_a_cashier_cannot_export(): void
    {
        $this->actingAs($this->cashier)->get(route('reports.export', ['format' => 'pdf']))->assertForbidden();
        $this->assertSame(0, ReportExport::count());
    }
}
