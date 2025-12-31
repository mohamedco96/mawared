<?php

namespace Database\Factories;

use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductCategoryFactory extends Factory
{
    protected $model = ProductCategory::class;

    public function definition(): array
    {
        $name = $this->faker->randomElement([
            'أطباق وصحون',
            'أكواب وفناجين',
            'أواني طبخ',
            'أدوات المائدة',
            'أدوات المطبخ',
            'علب حفظ',
        ]);

        return [
            'name' => $name,
            'name_en' => Str::slug($name),
            'slug' => Str::slug($name),
            'description' => $this->faker->sentence(),
            'image' => 'https://picsum.photos/seed/' . Str::random(10) . '/400/400',
            'is_active' => true,
            'display_order' => $this->faker->numberBetween(1, 100),
        ];
    }
}
