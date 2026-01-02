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
                'name' => 'مخزن دمياط الرئيسي',
                'code' => 'WH-DM-001',
                'address' => 'دمياط - رأس البر',
            ],
            [
                'name' => 'مخزن المنصورة',
                'code' => 'WH-MNS-001',
                'address' => 'المنصورة - المشاية',
            ],
            [
                'name' => 'مخزن طلخا',
                'code' => 'WH-TLK-001',
                'address' => 'طلخا - وسط البلد',
            ],
        ];

        foreach ($warehouses as $warehouse) {
            Warehouse::create($warehouse);
        }
    }
}
