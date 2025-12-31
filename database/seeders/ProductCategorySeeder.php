<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'أطباق وصحون',
                'name_en' => 'Plates and Dishes',
                'slug' => 'plates-dishes',
                'description' => 'مجموعة متنوعة من الأطباق والصحون للتقديم',
                'image' => 'https://picsum.photos/seed/plates/400/400',
                'is_active' => true,
                'display_order' => 1,
            ],
            [
                'name' => 'أكواب وفناجين',
                'name_en' => 'Cups and Mugs',
                'slug' => 'cups-mugs',
                'description' => 'أكواب وفناجين للمشروبات الساخنة والباردة',
                'image' => 'https://picsum.photos/seed/cups/400/400',
                'is_active' => true,
                'display_order' => 2,
            ],
            [
                'name' => 'أواني طبخ',
                'name_en' => 'Cooking Pots',
                'slug' => 'cooking-pots',
                'description' => 'أواني الطبخ والمقالي عالية الجودة',
                'image' => 'https://picsum.photos/seed/pots/400/400',
                'is_active' => true,
                'display_order' => 3,
            ],
            [
                'name' => 'أدوات المائدة',
                'name_en' => 'Cutlery',
                'slug' => 'cutlery',
                'description' => 'ملاعق وشوك وسكاكين من الستانلس ستيل',
                'image' => 'https://picsum.photos/seed/cutlery/400/400',
                'is_active' => true,
                'display_order' => 4,
            ],
            [
                'name' => 'أدوات المطبخ',
                'name_en' => 'Kitchen Tools',
                'slug' => 'kitchen-tools',
                'description' => 'أدوات متنوعة للمطبخ',
                'image' => 'https://picsum.photos/seed/tools/400/400',
                'is_active' => true,
                'display_order' => 5,
            ],
            [
                'name' => 'علب حفظ',
                'name_en' => 'Storage Containers',
                'slug' => 'storage-containers',
                'description' => 'علب حفظ الطعام البلاستيكية والزجاجية',
                'image' => 'https://picsum.photos/seed/storage/400/400',
                'is_active' => true,
                'display_order' => 6,
            ],
            [
                'name' => 'أدوات متنوعة',
                'name_en' => 'Miscellaneous',
                'slug' => 'miscellaneous',
                'description' => 'أدوات ومنتجات متنوعة للمطبخ والمنزل',
                'image' => 'https://picsum.photos/seed/misc/400/400',
                'is_active' => true,
                'display_order' => 7,
            ],
        ];

        foreach ($categories as $category) {
            ProductCategory::create($category);
        }
    }
}
