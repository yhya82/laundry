<?php

use App\Http\Controllers\ReceiptPdfController;
use App\Http\Controllers\ReportExportController;
use App\Livewire\Catalogue\ClothingTypes;
use App\Livewire\Catalogue\Machines;
use App\Livewire\Catalogue\Packages;
use App\Livewire\Catalogue\Services;
use App\Livewire\Collections\Index as CollectionsIndex;
use App\Livewire\Customers\Index as CustomersIndex;
use App\Livewire\Customers\Show as CustomerShow;
use App\Livewire\Damage\Index as DamageIndex;
use App\Livewire\Damage\Show as DamageShow;
use App\Livewire\Dashboard;
use App\Livewire\Delivery\Index as DeliveryIndex;
use App\Livewire\Delivery\Show as DeliveryShow;
use App\Livewire\Discounts\Templates as DiscountTemplates;
use App\Livewire\Employees\Departments;
use App\Livewire\Employees\Index as EmployeesIndex;
use App\Livewire\Expenses\Categories as ExpenseCategories;
use App\Livewire\Expenses\Index as ExpensesIndex;
use App\Livewire\Expenses\Schedules as ExpenseSchedules;
use App\Livewire\Notifications\Preferences as NotificationPreferences;
use App\Livewire\Orders\Queue as OrdersQueue;
use App\Livewire\Orders\Show as OrderShow;
use App\Livewire\Orders\Terminal as OrdersTerminal;
use App\Livewire\Payments\Index as PaymentsIndex;
use App\Livewire\Reports\Index as ReportsIndex;
use App\Livewire\Settings\Index as SettingsIndex;
use App\Livewire\Subscriptions\Index as SubscriptionsIndex;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    // Catalogue & configuration (Phase 4) — real screens, permission-gated.
    Route::middleware('can:packages.view')->group(function () {
        Route::get('/packages', Packages::class)->name('packages.index');
        Route::get('/packages/clothing-types', ClothingTypes::class)->name('packages.clothing-types');
        Route::get('/packages/services', Services::class)->name('packages.services');
        Route::get('/packages/machines', Machines::class)->name('packages.machines');
    });

    Route::middleware('can:employees.view')->group(function () {
        Route::get('/employees', EmployeesIndex::class)->name('employees.index');
        Route::get('/employees/departments', Departments::class)->name('employees.departments');
    });

    Route::get('/settings', SettingsIndex::class)->name('settings.index')->middleware('can:settings.view');

    Route::middleware('can:customers.view')->group(function () {
        Route::get('/customers', CustomersIndex::class)->name('customers.index');
        Route::get('/customers/{customer}', CustomerShow::class)->name('customers.show');
    });

    // Laundry orders & POS terminal (Phase 6). /orders/terminal must be
    // registered before /orders/{order} — otherwise the wildcard route
    // would swallow "terminal" as an order-ID segment.
    Route::middleware('can:orders.view')->group(function () {
        Route::get('/orders', OrdersQueue::class)->name('orders.index');
        Route::get('/orders/terminal', OrdersTerminal::class)->name('orders.terminal')->middleware('can:orders.create');
        Route::get('/orders/{order}', OrderShow::class)->name('orders.show');
    });

    // Payments, refunds, store credit & receipts (Phase 8).
    Route::get('/payments', PaymentsIndex::class)->name('payments.index')->middleware('can:payments.view');
    Route::get('/receipts/{receipt}/pdf', [ReceiptPdfController::class, 'show'])->name('receipts.pdf')->middleware('can:receipts.view');

    // Damage reports (Phase 9). /damage/{report} must be registered after
    // /damage — same wildcard-vs-static-segment ordering note as /orders.
    Route::middleware('can:damage.view')->group(function () {
        Route::get('/damage', DamageIndex::class)->name('damage.index');
        Route::get('/damage/{report}', DamageShow::class)->name('damage.show');
    });

    // Deliveries (Phase 10). Named "deliveries.*" (plural) to match the
    // Phase 3 sidebar entry and permission slugs (deliveries.view/.manage),
    // unlike damage.* above which is already singular in the seeder.
    Route::middleware('can:deliveries.view')->group(function () {
        Route::get('/deliveries', DeliveryIndex::class)->name('deliveries.index');
        Route::get('/deliveries/{delivery}', DeliveryShow::class)->name('deliveries.show');
    });

    // Expenses (Phase 11): categories/schedules/expenses share one tabbed
    // nav (x-tabs), mirroring /packages' sub-route pattern rather than the
    // Index+Show shape used by Damage/Delivery — expenses aren't tied to a
    // single order/customer the way those are.
    Route::middleware('can:expenses.view')->group(function () {
        Route::get('/expenses', ExpensesIndex::class)->name('expenses.index');
        Route::get('/expenses/schedules', ExpenseSchedules::class)->name('expenses.schedules');
        Route::get('/expenses/categories', ExpenseCategories::class)->name('expenses.categories');
    });

    // Self-service, no permission gate — see Preferences::class docblock.
    Route::get('/notifications/preferences', NotificationPreferences::class)->name('notifications.preferences');

    // Reporting & dashboards (Phase 13). /reports/export/{format} is a
    // plain controller route, not Livewire — file downloads need a real
    // HTTP response stream, which a Livewire component action can't return.
    Route::get('/reports', ReportsIndex::class)->name('reports.index')->middleware('can:reports.view');
    Route::get('/reports/export/{format}', [ReportExportController::class, '__invoke'])
        ->name('reports.export')
        ->middleware('can:reports.export')
        ->where('format', 'pdf|csv|excel');

    Route::get('/discounts', DiscountTemplates::class)->name('discounts.index')->middleware('can:discounts.manage');

    Route::get('/subscriptions', SubscriptionsIndex::class)->name('subscriptions.index')->middleware('can:subscriptions.view');
    Route::get('/collections', CollectionsIndex::class)->name('collections.index')->middleware('can:collections.manage');

    // Placeholder destinations for the sidebar built in Phase 3 — each
    // module's real screens replace these one at a time in Phases 8-13.
    // Permission-gated via `can:` so the shell's access control is real,
    // not just visual.
    $placeholders = [
        'users' => ['users.manage', 'Users', 'User accounts and role assignment.'],
    ];

    foreach ($placeholders as $prefix => [$permission, $title, $description]) {
        Route::get("/{$prefix}", function () use ($permission, $title, $description) {
            return view('pages.coming-soon', compact('permission', 'title', 'description'));
        })->name("{$prefix}.index")->middleware("can:{$permission}");
    }
});
