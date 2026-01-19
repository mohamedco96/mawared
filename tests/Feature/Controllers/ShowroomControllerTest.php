<?php

namespace Tests\Feature\Controllers;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowroomControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_showroom_index_retail_returns_view()
    {
        // ARRANGE
        Product::factory()->create([
            'is_visible_in_retail_catalog' => true,
            'name' => 'Retail Product'
        ]);

        // ACT
        $response = $this->get(route('showroom.retail'));

        // ASSERT
        $response->assertOk();
        $response->assertViewIs('showroom.index');
        $response->assertViewHas('mode', 'retail');
        $response->assertViewHas('products');
    }

    public function test_showroom_index_wholesale_returns_view()
    {
        // ARRANGE
        Product::factory()->create([
            'is_visible_in_wholesale_catalog' => true,
            'name' => 'Wholesale Product'
        ]);

        // ACT
        $response = $this->get(route('showroom.wholesale'));

        // ASSERT
        $response->assertOk();
        $response->assertViewIs('showroom.index');
        $response->assertViewHas('mode', 'wholesale');
    }

    public function test_showroom_returns_404_for_invalid_mode()
    {
        // ACT
        $response = $this->get('/showroom/invalid-mode');

        // ASSERT
        $response->assertNotFound();
    }
}
