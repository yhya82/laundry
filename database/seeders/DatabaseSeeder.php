<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Deliberately does NOT seed a bootstrap admin here — see
     * app/Console/Commands/SeedProductionAdmin.php and
     * MASTER_SPECIFICATION.md §10.4 §8's own warning against ever
     * re-running a placeholder-hash admin seed against a live database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            DepartmentSeeder::class,
            DamageTypeSeeder::class,
            ExpenseCategorySeeder::class,
            SettingSeeder::class,
        ]);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
