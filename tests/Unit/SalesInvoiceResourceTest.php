<?php

namespace Tests\Unit;

use App\Filament\Resources\SalesInvoiceResource;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Tests\TestCase;
use Mockery;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SalesInvoiceResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_recalculate_totals_logic_accesses_parent_items_and_sets_correct_paths()
    {
        // Mock data
        $items = [
            'uuid1' => ['total' => 100],
            'uuid2' => ['total' => 200],
        ];

        // Mock Set
        $setMock = Mockery::mock(Set::class);
        // Expect calls with prefix logic
        // Since we are mocking inside repeater (items = null), it should use prefix '../../'
        
        $setMock->shouldReceive('__invoke')->with('../../subtotal', 300)->once();
        $setMock->shouldReceive('__invoke')->with('../../discount', 0)->once();
        $setMock->shouldReceive('__invoke')->with('../../total', 300)->once();
        $setMock->shouldReceive('__invoke')->with('../../commission_amount', 0)->once(); // 0% of 300
        // SalesInvoiceResource does not set paid_amount for cash payments in recalculateTotals (it handles it in dehydrate/create)
        // $setMock->shouldReceive('__invoke')->with('../../paid_amount', 300)->once(); 
        $setMock->shouldReceive('__invoke')->with('../../remaining_amount', 0)->once();

        // Mock Get
        $getMock = Mockery::mock(Get::class);
        
        // Simulate being inside repeater
        $getMock->shouldReceive('__invoke')->with('items')->andReturn(null);
        $getMock->shouldReceive('__invoke')->with('../../items')->andReturn($items);
        
        // Expect calls with prefix
        $getMock->shouldReceive('__invoke')->with('../../discount_type')->andReturn('fixed');
        $getMock->shouldReceive('__invoke')->with('../../discount_value')->andReturn(0);
        $getMock->shouldReceive('__invoke')->with('../../payment_method')->andReturn('cash');
        $getMock->shouldReceive('__invoke')->with('../../paid_amount')->andReturn(0);
        $getMock->shouldReceive('__invoke')->with('../../commission_rate')->andReturn(0);

        // Access protected static method using reflection
        $method = new \ReflectionMethod(SalesInvoiceResource::class, 'recalculateTotals');
        $method->setAccessible(true);
        $method->invoke(null, $setMock, $getMock);
    }
}
