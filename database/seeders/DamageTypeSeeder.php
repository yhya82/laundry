<?php

namespace Database\Seeders;

use App\Models\DamageType;
use Illuminate\Database\Seeder;

/** Ports seed.sql §5 (MASTER_SPECIFICATION.md §10.4). */
class DamageTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'Torn / Ripped',
            'Stained',
            'Color Bleeding',
            'Shrinkage',
            'Missing Button/Zipper',
            'Burn / Melt Damage',
            'Lost Item',
            'Other',
        ];

        foreach ($types as $name) {
            DamageType::updateOrCreate(['name' => $name], ['name' => $name, 'status' => 'active']);
        }
    }
}
