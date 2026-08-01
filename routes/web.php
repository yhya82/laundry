<?php

use App\Http\Controllers\ReceiptPdfController;
use App\Livewire\Catalogue\ClothingTypes;
use App\Livewire\Catalogue\Machines;
use App\Livewire\Catalogue\Packages;
use App\Livewire\Catalogue\Services;
use App\Livewire\Collections\Index as CollectionsIndex;
use App\Livewire\Customers\Index as CustomersIndex;
use App\Livewire\Customers\Show as CustomerShow;
use App\Livewire\Dashboard;
use App\Livewire\Discounts\Templates as DiscountTemplates;
use App\Livewire\Employees\Departments;
use App\Livewire\Employees\Index as EmployeesIndex;
use App\Livewire\Orders\Queue as OrdersQueue;
use App\Livewire\Orders\Show as OrderShow;
use App\Livewire\Orders\Terminal as OrdersTerminal;
use App\Livewire\Payments\Index as PaymentsIndex;
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

    Route::get('/discounts', DiscountTemplates::class)->name('discounts.index')->middleware('can:discounts.manage');

    Route::get('/subscriptions', SubscriptionsIndex::class)->name('subscriptions.index')->middleware('can:subscriptions.view');
    Route::get('/collections', CollectionsIndex::class)->name('collections.index')->middleware('can:collections.manage');

    // Placeholder destinations for the sidebar built in Phase 3 — each
    // module's real screens replace these one at a time in Phases 8-13.
    // Permission-gated via `can:` so the shell's access control is real,
    // not just visual.
    $placeholders = [
        'damage' => ['damage.view', 'Damage Reports', 'Damage reports and resolution workflow.'],
        'deliveries' => ['deliveries.view', 'Deliveries', 'Pickup/delivery assignment and status.'],
        'expenses' => ['expenses.view', 'Expenses', 'Expense categories, schedules, and approvals.'],
        'reports' => ['reports.view', 'Reports', 'Role-based dashboards and exports.'],
        'users' => ['users.manage', 'Users', 'User accounts and role assignment.'],
    ];

    foreach ($placeholders as $prefix => [$permission, $title, $description]) {
        Route::get("/{$prefix}", function () use ($permission, $title, $description) {
            return view('pages.coming-soon', compact('permission', 'title', 'description'));
        })->name("{$prefix}.index")->middleware("can:{$permission}");
    }
});
