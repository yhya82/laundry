<?php

use App\Livewire\Catalogue\ClothingTypes;
use App\Livewire\Catalogue\Machines;
use App\Livewire\Catalogue\Packages;
use App\Livewire\Catalogue\Services;
use App\Livewire\Dashboard;
use App\Livewire\Employees\Departments;
use App\Livewire\Employees\Index as EmployeesIndex;
use App\Livewire\Settings\Index as SettingsIndex;
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

    // Placeholder destinations for the sidebar built in Phase 3 — each
    // module's real screens replace these one at a time in Phases 5-13.
    // Permission-gated via `can:` so the shell's access control is real,
    // not just visual.
    $placeholders = [
        'customers' => ['customers.view', 'Customers', 'Customer profiles, addresses, notes, and timeline.'],
        'orders' => ['orders.view', 'Laundry Orders', 'The POS terminal and processing queue.'],
        'subscriptions' => ['subscriptions.view', 'Subscriptions', 'Recurring pickup schedules and package plans.'],
        'collections' => ['collections.manage', 'Collections', 'Scheduled pickups and their conversion into orders.'],
        'payments' => ['payments.view', 'Payments', 'Payments, refunds, and store credit.'],
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
