<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $units = [
            ['name' => 'قطعة', 'symbol' => 'قطعة'],
            ['name' => 'كرتونة', 'symbol' => 'كرتونة'],
        ];

        foreach ($units as $unit) {
            Unit::create($unit);
        }
    }
}
