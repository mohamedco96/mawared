<?php

namespace Tests\Unit;

use App\Filament\Resources\PurchaseInvoiceResource;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PurchaseInvoiceResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_recalculate_totals_logic_accesses_parent_items()
    {
        // Mock data
        $items = [
            'uuid1' => ['total' => 100],
            'uuid2' => ['total' => 200],
        ];

        // Mock Set
        $setMock = Mockery::mock(Set::class);
        $setMock->shouldReceive('__invoke')->with('../../subtotal', 300)->once();
        $setMock->shouldReceive('__invoke')->with('../../discount', 0)->once();
        $setMock->shouldReceive('__invoke')->with('../../total', 300)->once();
        $setMock->shouldReceive('__invoke')->with('../../paid_amount', 300)->once();
        $setMock->shouldReceive('__invoke')->with('../../remaining_amount', 0)->once();
        $setMock->shouldReceive('__invoke')->with('commission_amount', Mockery::any()); // In case it's called (SalesInvoice has it, Purchase might not)

        // Mock Get
        $getMock = Mockery::mock(Get::class);
        $getMock->shouldReceive('__invoke')->with('items')->andReturn(null);
        $getMock->shouldReceive('__invoke')->with('../../items')->andReturn($items);
        $getMock->shouldReceive('__invoke')->with('../../discount_type')->andReturn('fixed');
        $getMock->shouldReceive('__invoke')->with('../../discount_value')->andReturn(0);
        $getMock->shouldReceive('__invoke')->with('../../payment_method')->andReturn('cash');
        $getMock->shouldReceive('__invoke')->with('../../paid_amount')->andReturn(0);
        $getMock->shouldReceive('__invoke')->with('commission_rate')->andReturn(0);

        // Access protected static method using reflection
        $method = new \ReflectionMethod(PurchaseInvoiceResource::class, 'recalculateTotals');
        $method->setAccessible(true);
        $method->invoke(null, $setMock, $getMock);
    }

    public function test_recalculate_totals_handles_edge_cases()
    {
        // Edge cases: Zero totals, large numbers, decimals
        $items = [
            'uuid1' => ['total' => 0],
            'uuid2' => ['total' => 10.5555], // High precision
            'uuid3' => ['total' => 999999.99],
        ];

        $expectedSubtotal = 0 + 10.5555 + 999999.99; // 1000010.5455

        // Mock Set
        $setMock = Mockery::mock(Set::class);
        $setMock->shouldReceive('__invoke')->with('../../subtotal', $expectedSubtotal)->once();
        $setMock->shouldReceive('__invoke')->with('../../discount', 0)->once();
        $setMock->shouldReceive('__invoke')->with('../../total', $expectedSubtotal)->once();
        $setMock->shouldReceive('__invoke')->with('../../paid_amount', $expectedSubtotal)->once();
        $setMock->shouldReceive('__invoke')->with('../../remaining_amount', 0)->once();

        // Mock Get
        $getMock = Mockery::mock(Get::class);
        $getMock->shouldReceive('__invoke')->with('items')->andReturn(null);
        $getMock->shouldReceive('__invoke')->with('../../items')->andReturn($items);
        $getMock->shouldReceive('__invoke')->with('../../discount_type')->andReturn('fixed');
        $getMock->shouldReceive('__invoke')->with('../../discount_value')->andReturn(0);
        $getMock->shouldReceive('__invoke')->with('../../payment_method')->andReturn('cash');
        $getMock->shouldReceive('__invoke')->with('../../paid_amount')->andReturn(0);

        $method = new \ReflectionMethod(PurchaseInvoiceResource::class, 'recalculateTotals');
        $method->setAccessible(true);
        $method->invoke(null, $setMock, $getMock);
    }
}
