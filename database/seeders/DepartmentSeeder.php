<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

/** Ports seed.sql §4 (MASTER_SPECIFICATION.md §10.4). */
class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['name' => 'Laundry Processing', 'description' => 'Washing, drying, ironing, and quality-check staff.'],
            ['name' => 'Delivery', 'description' => 'Pickup and delivery staff.'],
            ['name' => 'Customer Service', 'description' => 'Front-of-house / cashier staff.'],
            ['name' => 'Finance', 'description' => 'Expense and payment administration.'],
            ['name' => 'Management', 'description' => 'Branch and operations management.'],
        ];

        foreach ($departments as $department) {
            Department::updateOrCreate(['name' => $department['name']], $department);
        }
    }
}
