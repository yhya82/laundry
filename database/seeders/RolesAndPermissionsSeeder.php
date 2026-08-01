<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Ports seed.sql §1-3 (MASTER_SPECIFICATION.md §10.4) — default roles, the
 * permission catalogue, and role -> permission mapping.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Administrator', 'slug' => 'administrator', 'description' => 'Full system access, including user management, settings, and financial data.', 'is_system' => true, 'status' => 'active'],
            ['name' => 'Manager', 'slug' => 'manager', 'description' => 'Approves discounts and damage resolutions, manages employees, customers, and the laundry queue.', 'is_system' => true, 'status' => 'active'],
            ['name' => 'Cashier', 'slug' => 'cashier', 'description' => 'Creates walk-in orders, receives payments, applies pre-approved discounts.', 'is_system' => true, 'status' => 'active'],
            ['name' => 'Laundry Staff', 'slug' => 'laundry-staff', 'description' => 'Views assigned laundry, updates processing stages, reports damage.', 'is_system' => true, 'status' => 'active'],
            ['name' => 'Delivery Staff', 'slug' => 'delivery-staff', 'description' => 'Views assigned deliveries, updates delivery status.', 'is_system' => true, 'status' => 'active'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['slug' => $role['slug']], $role);
        }

        $permissions = [
            // Customers
            ['name' => 'View Customers', 'slug' => 'customers.view', 'permission_group' => 'customers', 'description' => 'View customer profiles and history.'],
            ['name' => 'Create Customers', 'slug' => 'customers.create', 'permission_group' => 'customers', 'description' => 'Create new customer profiles.'],
            ['name' => 'Update Customers', 'slug' => 'customers.update', 'permission_group' => 'customers', 'description' => 'Edit existing customer profiles.'],
            ['name' => 'Deactivate Customers', 'slug' => 'customers.deactivate', 'permission_group' => 'customers', 'description' => 'Soft-delete/deactivate a customer.'],
            // Orders
            ['name' => 'View Orders', 'slug' => 'orders.view', 'permission_group' => 'orders', 'description' => 'View laundry orders.'],
            ['name' => 'Create Orders', 'slug' => 'orders.create', 'permission_group' => 'orders', 'description' => 'Create walk-in or subscription-derived orders.'],
            ['name' => 'Update Orders', 'slug' => 'orders.update', 'permission_group' => 'orders', 'description' => 'Edit order details/instructions.'],
            ['name' => 'Cancel Orders', 'slug' => 'orders.cancel', 'permission_group' => 'orders', 'description' => 'Cancel a laundry order.'],
            ['name' => 'Advance Order Stage', 'slug' => 'orders.advance_stage', 'permission_group' => 'orders', 'description' => 'Move an order to its next processing stage.'],
            ['name' => 'Assign Order Staff', 'slug' => 'orders.assign_staff', 'permission_group' => 'orders', 'description' => 'Assign staff/machine to an order.'],
            // Subscriptions & Collections
            ['name' => 'View Subscriptions', 'slug' => 'subscriptions.view', 'permission_group' => 'subscriptions', 'description' => 'View subscription details.'],
            ['name' => 'Manage Subscriptions', 'slug' => 'subscriptions.manage', 'permission_group' => 'subscriptions', 'description' => 'Create/pause/resume/cancel subscriptions.'],
            ['name' => 'Manage Collections', 'slug' => 'collections.manage', 'permission_group' => 'collections', 'description' => 'Schedule, reschedule, and convert collections.'],
            // Packages & Catalogue
            ['name' => 'View Packages', 'slug' => 'packages.view', 'permission_group' => 'catalogue', 'description' => 'View packages, clothing types, services.'],
            ['name' => 'Manage Packages', 'slug' => 'packages.manage', 'permission_group' => 'catalogue', 'description' => 'Create/edit packages, clothing types, services, prices.'],
            // Payments & Financial
            ['name' => 'View Payments', 'slug' => 'payments.view', 'permission_group' => 'financial', 'description' => 'View payment records.'],
            ['name' => 'Create Payments', 'slug' => 'payments.create', 'permission_group' => 'financial', 'description' => 'Record a payment against an order.'],
            ['name' => 'Process Refunds', 'slug' => 'payments.refund', 'permission_group' => 'financial', 'description' => 'Issue a refund against a payment.'],
            ['name' => 'Manage Store Credit', 'slug' => 'payments.store_credit', 'permission_group' => 'financial', 'description' => 'Adjust customer store credit balances.'],
            ['name' => 'View Financial Reports', 'slug' => 'financial.reports.view', 'permission_group' => 'financial', 'description' => 'View revenue/payment/discount/expense reports.'],
            // Discounts
            ['name' => 'Apply Discounts', 'slug' => 'discounts.apply', 'permission_group' => 'discounts', 'description' => 'Apply a pre-approved discount template.'],
            ['name' => 'Approve Discounts', 'slug' => 'discounts.approve', 'permission_group' => 'discounts', 'description' => 'Approve a custom/manual discount above the cashier threshold.'],
            ['name' => 'Manage Discount Templates', 'slug' => 'discounts.manage', 'permission_group' => 'discounts', 'description' => 'Create/edit discount templates.'],
            // Receipts
            ['name' => 'View Receipts', 'slug' => 'receipts.view', 'permission_group' => 'receipts', 'description' => 'View and search receipts.'],
            ['name' => 'Print Receipts', 'slug' => 'receipts.print', 'permission_group' => 'receipts', 'description' => 'Print/reprint a receipt.'],
            ['name' => 'Cancel Receipts', 'slug' => 'receipts.cancel', 'permission_group' => 'receipts', 'description' => 'Cancel a receipt (never hard-deleted).'],
            // Damage
            ['name' => 'View Damage Reports', 'slug' => 'damage.view', 'permission_group' => 'damage', 'description' => 'View damage reports.'],
            ['name' => 'Create Damage Reports', 'slug' => 'damage.create', 'permission_group' => 'damage', 'description' => 'Report damage against an order.'],
            ['name' => 'Approve Damage Resolution', 'slug' => 'damage.approve', 'permission_group' => 'damage', 'description' => 'Approve a damage resolution and compensation.'],
            // Delivery
            ['name' => 'View Deliveries', 'slug' => 'deliveries.view', 'permission_group' => 'delivery', 'description' => 'View delivery assignments/status.'],
            ['name' => 'Manage Deliveries', 'slug' => 'deliveries.manage', 'permission_group' => 'delivery', 'description' => 'Create/assign/update deliveries.'],
            // Employees & Users
            ['name' => 'View Employees', 'slug' => 'employees.view', 'permission_group' => 'employees', 'description' => 'View employee profiles.'],
            ['name' => 'Manage Employees', 'slug' => 'employees.manage', 'permission_group' => 'employees', 'description' => 'Create/edit/deactivate employee profiles.'],
            ['name' => 'Manage Users', 'slug' => 'users.manage', 'permission_group' => 'users', 'description' => 'Create/edit user accounts and role assignments.'],
            // Expenses
            ['name' => 'View Expenses', 'slug' => 'expenses.view', 'permission_group' => 'expenses', 'description' => 'View business expenses.'],
            ['name' => 'Create Expenses', 'slug' => 'expenses.create', 'permission_group' => 'expenses', 'description' => 'Record a new expense.'],
            ['name' => 'Approve Expenses', 'slug' => 'expenses.approve', 'permission_group' => 'expenses', 'description' => 'Approve an expense above the configured threshold.'],
            // Reports & Settings
            ['name' => 'View Reports', 'slug' => 'reports.view', 'permission_group' => 'reports', 'description' => 'Access the reporting dashboard.'],
            ['name' => 'Export Reports', 'slug' => 'reports.export', 'permission_group' => 'reports', 'description' => 'Export reports as PDF/Excel/CSV.'],
            ['name' => 'View Settings', 'slug' => 'settings.view', 'permission_group' => 'settings', 'description' => 'View system settings.'],
            ['name' => 'Manage Settings', 'slug' => 'settings.manage', 'permission_group' => 'settings', 'description' => 'Change system settings.'],
            ['name' => 'View Audit Log', 'slug' => 'audit.view', 'permission_group' => 'audit', 'description' => 'View the system-wide activity/audit log.'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['slug' => $permission['slug']], $permission);
        }

        $administrator = Role::where('slug', 'administrator')->firstOrFail();
        $administrator->permissions()->sync(Permission::pluck('id'));

        $manager = Role::where('slug', 'manager')->firstOrFail();
        $manager->permissions()->sync(
            Permission::whereNotIn('slug', ['users.manage', 'settings.manage', 'audit.view'])->pluck('id')
        );

        $cashier = Role::where('slug', 'cashier')->firstOrFail();
        $cashier->permissions()->sync(
            Permission::whereIn('slug', [
                'customers.view', 'customers.create', 'customers.update',
                'orders.view', 'orders.create', 'orders.update',
                'packages.view',
                'payments.view', 'payments.create',
                'discounts.apply',
                'receipts.view', 'receipts.print',
                'damage.view', 'damage.create',
                'subscriptions.view', 'collections.manage',
            ])->pluck('id')
        );

        $laundryStaff = Role::where('slug', 'laundry-staff')->firstOrFail();
        $laundryStaff->permissions()->sync(
            Permission::whereIn('slug', [
                'orders.view', 'orders.advance_stage',
                'customers.view',
                'damage.view', 'damage.create',
            ])->pluck('id')
        );

        $deliveryStaff = Role::where('slug', 'delivery-staff')->firstOrFail();
        $deliveryStaff->permissions()->sync(
            Permission::whereIn('slug', [
                'deliveries.view', 'deliveries.manage',
                'orders.view',
                'customers.view',
            ])->pluck('id')
        );
    }
}
