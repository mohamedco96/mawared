<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $warehouses = [
            [
                'name' => 'مخزن القاهرة الرئيسي',
                'code' => 'WH-CAI-001',
                'address' => 'القاهرة - مدينة نصر',
            ],
            [
                'name' => 'مخزن الإسكندرية',
                'code' => 'WH-ALX-001',
                'address' => 'الإسكندرية - سموحة',
            ],
            [
                'name' => 'مخزن الجيزة',
                'code' => 'WH-GIZ-001',
                'address' => 'الجيزة - المهندسين',
            ],
        ];

        foreach ($warehouses as $warehouse) {
            Warehouse::create($warehouse);
        }
    }
}
