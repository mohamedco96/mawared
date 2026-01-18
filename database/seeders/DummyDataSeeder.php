<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DummyDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Disable foreign key checks to avoid issues with insertion order if needed,
        // but we will try to insert in a sane order.
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        $this->seedUsers();
        $this->seedUnits();
        $this->seedProductCategories();
        $this->seedWarehouses();
        $this->seedTreasuries();
        $this->seedPartners();
        $this->seedProducts();

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function seedUsers()
    {
        // Check if admin user exists
        $adminExists = \App\Models\User::where('email', 'admin@mawared.test')->exists();

        if (!$adminExists) {
            \App\Models\User::create([
                'name' => 'Test Admin',
                'email' => 'admin@mawared.test',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]);
        }
    }

    private function seedUnits()
    {
        $data = [
            [
                "id" => "01kf8tqk1t3hxj3q9jw1ab0g87",
                "name" => "قطعة",
                "symbol" => null,
                "created_at" => "2026-01-18 17:14:15",
                "updated_at" => "2026-01-18 17:14:15"
            ],
            [
                "id" => "01kf8tqqph1w96yehh77ppadgc",
                "name" => "كرتونة",
                "symbol" => null,
                "created_at" => "2026-01-18 17:14:20",
                "updated_at" => "2026-01-18 17:14:20"
            ]
        ];

        foreach ($data as $item) {
            DB::table('units')->updateOrInsert(['id' => $item['id']], $item);
        }
    }

    private function seedProductCategories()
    {
        $data = [
            [
                "id" => "01kf8trk47tqr1ggm52n6sbfbm",
                "parent_id" => null,
                "name" => "عام",
                "name_en" => null,
                "slug" => "aaam",
                "description" => null,
                "image" => null,
                "is_active" => 1,
                "display_order" => 0,
                "default_profit_margin" => null,
                "created_at" => "2026-01-18 17:14:48",
                "updated_at" => "2026-01-18 17:14:48",
                "deleted_at" => null
            ]
        ];

        foreach ($data as $item) {
            DB::table('product_categories')->updateOrInsert(['id' => $item['id']], $item);
        }
    }

    private function seedWarehouses()
    {
        $data = [
            [
                "id" => "01kf8tr0jk86qq1nn3fes5szwv",
                "name" => "رئيسي",
                "code" => "WH-696CF8D58F7C5",
                "address" => null,
                "is_active" => 1,
                "created_at" => "2026-01-18 17:14:29",
                "updated_at" => "2026-01-18 17:14:29"
            ]
        ];

        foreach ($data as $item) {
            DB::table('warehouses')->updateOrInsert(['id' => $item['id']], $item);
        }
    }

    private function seedTreasuries()
    {
        $data = [
            [
                "id" => "01kf8tsfbnbrb7539yhyc3rhva",
                "name" => "رئيسي",
                "type" => "cash",
                "description" => null,
                "created_at" => "2026-01-18 17:15:17",
                "updated_at" => "2026-01-18 17:15:17"
            ]
        ];

        foreach ($data as $item) {
            DB::table('treasuries')->updateOrInsert(['id' => $item['id']], $item);
        }
    }

    private function seedPartners()
    {
        $data = [
            [
                "id" => "01kf8tv7dyrzpn96v1hh9zkvg8",
                "legacy_id" => null,
                "name" => "شريك 1",
                "phone" => null,
                "type" => "shareholder",
                "gov_id" => null,
                "region" => null,
                "address" => null,
                "is_banned" => 0,
                "current_balance" => "0.0000",
                "current_capital" => "0.0000",
                "equity_percentage" => null,
                "is_manager" => 0,
                "monthly_salary" => null,
                "opening_balance" => "0.0000",
                "created_at" => "2026-01-18 17:16:14",
                "updated_at" => "2026-01-18 17:16:14",
                "deleted_at" => null
            ],
            [
                "id" => "01kf8tvgspqc56zqgnvjah0thq",
                "legacy_id" => null,
                "name" => "شريك 2",
                "phone" => null,
                "type" => "shareholder",
                "gov_id" => null,
                "region" => null,
                "address" => null,
                "is_banned" => 0,
                "current_balance" => "0.0000",
                "current_capital" => "0.0000",
                "equity_percentage" => null,
                "is_manager" => 0,
                "monthly_salary" => null,
                "opening_balance" => "0.0000",
                "created_at" => "2026-01-18 17:16:24",
                "updated_at" => "2026-01-18 17:16:24",
                "deleted_at" => null
            ],
            [
                "id" => "01kf8tvqd2t1211qhteqjy52pn",
                "legacy_id" => null,
                "name" => "مورد",
                "phone" => null,
                "type" => "supplier",
                "gov_id" => null,
                "region" => null,
                "address" => null,
                "is_banned" => 0,
                "current_balance" => "0.0000",
                "current_capital" => "0.0000",
                "equity_percentage" => null,
                "is_manager" => 0,
                "monthly_salary" => null,
                "opening_balance" => "0.0000",
                "created_at" => "2026-01-18 17:16:31",
                "updated_at" => "2026-01-18 17:16:31",
                "deleted_at" => null
            ],
            [
                "id" => "01kf8tvw3e6sz7qsc2c4yc5ssx",
                "legacy_id" => null,
                "name" => "عميل",
                "phone" => null,
                "type" => "customer",
                "gov_id" => null,
                "region" => null,
                "address" => null,
                "is_banned" => 0,
                "current_balance" => "0.0000",
                "current_capital" => "0.0000",
                "equity_percentage" => null,
                "is_manager" => 0,
                "monthly_salary" => null,
                "opening_balance" => "0.0000",
                "created_at" => "2026-01-18 17:16:36",
                "updated_at" => "2026-01-18 17:16:36",
                "deleted_at" => null
            ]
        ];

        foreach ($data as $item) {
            DB::table('partners')->updateOrInsert(['id' => $item['id']], $item);
        }
    }

    private function seedProducts()
    {
        $data = [
            [
                "id" => "01kf8ttegywv4jdrpzyj9m9p1j",
                "category_id" => "01kf8trk47tqr1ggm52n6sbfbm",
                "name" => "منتج متعدد الوحدات",
                "description" => null,
                "image" => null,
                "images" => "[]",
                "barcode" => "BC7493495SQL",
                "large_barcode" => "LB749349ONVU",
                "sku" => "SKU749349T2JM",
                "min_stock" => 0,
                "avg_cost" => "0.0000",
                "is_visible_in_retail_catalog" => 1,
                "is_visible_in_wholesale_catalog" => 1,
                "small_unit_id" => "01kf8tqk1t3hxj3q9jw1ab0g87",
                "large_unit_id" => "01kf8tqqph1w96yehh77ppadgc",
                "factor" => 10,
                "retail_price" => "100.0000",
                "wholesale_price" => "90.0000",
                "large_retail_price" => "1000.0000",
                "large_wholesale_price" => "900.0000",
                "created_at" => "2026-01-18 17:15:49",
                "updated_at" => "2026-01-18 17:15:49",
                "deleted_at" => null
            ]
        ];

        foreach ($data as $item) {
            DB::table('products')->updateOrInsert(['id' => $item['id']], $item);
        }
    }
}
