<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Ports seed.sql §7 (MASTER_SPECIFICATION.md §10.4). Values are reasonable
 * placeholders — replace during initial configuration, not meant to be
 * used as-is in production.
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['setting_group' => 'general', 'setting_key' => 'business_name', 'setting_value' => 'My Laundry Business', 'value_type' => 'string', 'description' => 'Displayed on receipts, login page, and sidebar.'],
            ['setting_group' => 'general', 'setting_key' => 'currency', 'setting_value' => 'USD', 'value_type' => 'string', 'description' => 'ISO currency code used throughout the system.'],
            ['setting_group' => 'general', 'setting_key' => 'timezone', 'setting_value' => 'UTC', 'value_type' => 'string', 'description' => 'Canonical timezone for all DATETIME values — set once, do not change after go-live.'],
            ['setting_group' => 'general', 'setting_key' => 'receipt_footer', 'setting_value' => 'Thank you for your business!', 'value_type' => 'string', 'description' => 'Printed at the bottom of every receipt.'],
            ['setting_group' => 'laundry', 'setting_key' => 'max_processing_packages', 'setting_value' => '10', 'value_type' => 'integer', 'description' => 'Maximum packages allowed simultaneously in each processing stage.'],
            ['setting_group' => 'laundry', 'setting_key' => 'express_enabled', 'setting_value' => 'true', 'value_type' => 'boolean', 'description' => 'Whether express service is offered.'],
            ['setting_group' => 'laundry', 'setting_key' => 'staff_assignment_required', 'setting_value' => 'false', 'value_type' => 'boolean', 'description' => 'Whether an order must have an assigned staff member before processing can begin.'],
            ['setting_group' => 'discount', 'setting_key' => 'max_cashier_discount_percent', 'setting_value' => '10', 'value_type' => 'integer', 'description' => 'Above this percentage, a discount requires manager approval.'],
            ['setting_group' => 'security', 'setting_key' => 'max_failed_login_attempts', 'setting_value' => '5', 'value_type' => 'integer', 'description' => 'Failed login attempts before an account is locked (see users.failed_login_attempts/locked_until).'],
            ['setting_group' => 'security', 'setting_key' => 'account_lockout_minutes', 'setting_value' => '30', 'value_type' => 'integer', 'description' => 'How long an account stays locked after exceeding max_failed_login_attempts.'],
            ['setting_group' => 'notifications', 'setting_key' => 'in_app_enabled', 'setting_value' => 'true', 'value_type' => 'boolean', 'description' => 'Whether in-app real-time notifications are enabled.'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['setting_group' => $setting['setting_group'], 'setting_key' => $setting['setting_key']],
                $setting
            );
        }
    }
}
