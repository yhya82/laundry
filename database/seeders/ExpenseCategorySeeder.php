<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

/** Ports seed.sql §6 (MASTER_SPECIFICATION.md §10.4). */
class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Rent', 'description' => 'Facility/premises rent.'],
            ['name' => 'Utilities', 'description' => 'Electricity, water, gas.'],
            ['name' => 'Salaries', 'description' => 'Staff wages and salaries.'],
            ['name' => 'Supplies', 'description' => 'Detergent, packaging, and consumables.'],
            ['name' => 'Equipment Maintenance', 'description' => 'Machine repair and servicing.'],
            ['name' => 'Marketing', 'description' => 'Advertising and promotions.'],
            ['name' => 'Other', 'description' => 'Anything not covered above.'],
        ];

        foreach ($categories as $category) {
            ExpenseCategory::updateOrCreate(['name' => $category['name']], $category);
        }
    }
}
