<?php

namespace Tests\Feature\Controllers;

use App\Models\Partner;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_print_partner_statement_returns_view()
    {
        // ARRANGE
        $user = User::factory()->create();
        $partner = Partner::factory()->create();

        // Mock ReportService to avoid complex logic during controller test
        $this->mock(ReportService::class, function ($mock) {
            $mock->shouldReceive('getPartnerStatement')
                ->once()
                ->andReturn([
                    'partner' => (object) ['name' => 'Test Partner', 'code' => 'P001', 'phone' => '123456'],
                    'transactions' => [],
                    'opening_balance' => 0,
                    'closing_balance' => 0,
                    'total_debit' => 0,
                    'total_credit' => 0,
                    'from_date' => now()->subMonth()->toDateString(),
                    'to_date' => now()->toDateString(),
                ]);
        });

        // ACT
        $response = $this->actingAs($user)->get(route('reports.partner-statement.print', [
            'partner_id' => $partner->id,
            'from_date' => now()->subMonth()->toDateString(),
            'to_date' => now()->toDateString(),
        ]));

        // ASSERT
        $response->assertOk();
        $response->assertViewIs('reports.partner-statement');
        $response->assertViewHas('reportData');
    }

    public function test_print_stock_card_returns_view()
    {
        // ARRANGE
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();

        $this->mock(ReportService::class, function ($mock) {
            $mock->shouldReceive('getStockCard')
                ->once()
                ->andReturn([
                    'product' => (object) ['name' => 'Test Product', 'sku' => 'SKU001'],
                    'warehouse' => null,
                    'movements' => [],
                    'opening_stock' => 0,
                    'closing_stock' => 0,
                    'total_in' => 0,
                    'total_out' => 0,
                    'from_date' => now()->subMonth()->toDateString(),
                    'to_date' => now()->toDateString(),
                ]);
        });

        // ACT
        $response = $this->actingAs($user)->get(route('reports.stock-card.print', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'from_date' => now()->subMonth()->toDateString(),
            'to_date' => now()->toDateString(),
        ]));

        // ASSERT
        $response->assertOk();
        $response->assertViewIs('reports.stock-card');
    }
}
