<?php

namespace Tests\Unit;

use App\Filament\Resources\PurchaseInvoiceResource;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PurchaseInvoiceCalculationLogicTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Helper to invoke the protected static method
     */
    protected function invokeRecalculateTotals($setMock, $getMock)
    {
        $method = new \ReflectionMethod(PurchaseInvoiceResource::class, 'recalculateTotals');
        $method->setAccessible(true);
        $method->invoke(null, $setMock, $getMock);
    }

    public function test_basic_subtotal_calculation()
    {
        // Scenario: 2 items, simple integers
        $items = [
            '1' => ['total' => 100],
            '2' => ['total' => 200],
        ];
        // Expected: Subtotal 300, Total 300 (no discount)

        $setMock = Mockery::mock(Set::class);
        $setMock->shouldReceive('__invoke')->with('subtotal', 300)->once();
        $setMock->shouldReceive('__invoke')->with('discount', 0)->once();
        $setMock->shouldReceive('__invoke')->with('total', 300)->once();
        // Default cash payment behavior
        $setMock->shouldReceive('__invoke')->with('paid_amount', 300)->once();
        $setMock->shouldReceive('__invoke')->with('remaining_amount', 0)->once();

        $getMock = Mockery::mock(Get::class);
        $getMock->shouldReceive('__invoke')->with('items')->andReturn($items);
        $getMock->shouldReceive('__invoke')->with('discount_type')->andReturn('fixed');
        $getMock->shouldReceive('__invoke')->with('discount_value')->andReturn(0);
        $getMock->shouldReceive('__invoke')->with('payment_method')->andReturn('cash');
        $getMock->shouldReceive('__invoke')->with('paid_amount')->andReturn(0);

        $this->invokeRecalculateTotals($setMock, $getMock);
    }

    public function test_scope_fallback_works()
    {
        // Scenario: Called from inside repeater row, so 'items' is null, '../../items' has data
        $items = [
            '1' => ['total' => 50],
        ];

        $setMock = Mockery::mock(Set::class);
        // Expect calls with '../../' prefix
        $setMock->shouldReceive('__invoke')->with('../../subtotal', 50)->once();
        $setMock->shouldReceive('__invoke')->with('../../discount', 0)->once();
        $setMock->shouldReceive('__invoke')->with('../../total', 50)->once();
        $setMock->shouldReceive('__invoke')->with('../../paid_amount', 50)->once();
        $setMock->shouldReceive('__invoke')->with('../../remaining_amount', 0)->once();

        $getMock = Mockery::mock(Get::class);
        $getMock->shouldReceive('__invoke')->with('items')->andReturn(null);
        $getMock->shouldReceive('__invoke')->with('../../items')->andReturn($items);
        // Expect calls with prefix
        $getMock->shouldReceive('__invoke')->with('../../discount_type')->andReturn('fixed');
        $getMock->shouldReceive('__invoke')->with('../../discount_value')->andReturn(0);
        $getMock->shouldReceive('__invoke')->with('../../payment_method')->andReturn('cash');
        $getMock->shouldReceive('__invoke')->with('../../paid_amount')->andReturn(0);

        $this->invokeRecalculateTotals($setMock, $getMock);
    }

    public function test_fixed_discount_calculation()
    {
        // Scenario: Subtotal 1000, Discount 100 (Fixed)
        $items = ['1' => ['total' => 1000]];

        $setMock = Mockery::mock(Set::class);
        $setMock->shouldReceive('__invoke')->with('subtotal', 1000)->once();
        $setMock->shouldReceive('__invoke')->with('discount', 100.0)->once(); // The actual calculated discount
        $setMock->shouldReceive('__invoke')->with('total', 900.0)->once(); // 1000 - 100
        $setMock->shouldReceive('__invoke')->with('paid_amount', 900.0)->once();
        $setMock->shouldReceive('__invoke')->with('remaining_amount', 0)->once();

        $getMock = Mockery::mock(Get::class);
        $getMock->shouldReceive('__invoke')->with('items')->andReturn($items);
        $getMock->shouldReceive('__invoke')->with('discount_type')->andReturn('fixed');
        $getMock->shouldReceive('__invoke')->with('discount_value')->andReturn(100);
        $getMock->shouldReceive('__invoke')->with('payment_method')->andReturn('cash');
        $getMock->shouldReceive('__invoke')->with('paid_amount')->andReturn(0);

        $this->invokeRecalculateTotals($setMock, $getMock);
    }

    public function test_percentage_discount_calculation()
    {
        // Scenario: Subtotal 1000, Discount 10%
        $items = ['1' => ['total' => 1000]];

        $setMock = Mockery::mock(Set::class);
        $setMock->shouldReceive('__invoke')->with('subtotal', 1000)->once();
        $setMock->shouldReceive('__invoke')->with('discount', 100.0)->once(); // 10% of 1000
        $setMock->shouldReceive('__invoke')->with('total', 900.0)->once();
        $setMock->shouldReceive('__invoke')->with('paid_amount', 900.0)->once();
        $setMock->shouldReceive('__invoke')->with('remaining_amount', 0)->once();

        $getMock = Mockery::mock(Get::class);
        $getMock->shouldReceive('__invoke')->with('items')->andReturn($items);
        $getMock->shouldReceive('__invoke')->with('discount_type')->andReturn('percentage');
        $getMock->shouldReceive('__invoke')->with('discount_value')->andReturn(10);
        $getMock->shouldReceive('__invoke')->with('payment_method')->andReturn('cash');
        $getMock->shouldReceive('__invoke')->with('paid_amount')->andReturn(0);

        $this->invokeRecalculateTotals($setMock, $getMock);
    }

    public function test_credit_payment_full_remaining()
    {
        // Scenario: Total 1000, Payment Method 'credit', Paid Amount 0
        $items = ['1' => ['total' => 1000]];

        $setMock = Mockery::mock(Set::class);
        $setMock->shouldReceive('__invoke')->with('subtotal', 1000)->once();
        $setMock->shouldReceive('__invoke')->with('discount', 0)->once();
        $setMock->shouldReceive('__invoke')->with('total', 1000)->once();
        // Credit logic:
        // If currentPaidAmount (0) <= NetTotal (1000), update remaining.
        // It does NOT update paid_amount unless it exceeds total.
        $setMock->shouldReceive('__invoke')->with('remaining_amount', 1000)->once();

        $getMock = Mockery::mock(Get::class);
        $getMock->shouldReceive('__invoke')->with('items')->andReturn($items);
        $getMock->shouldReceive('__invoke')->with('discount_type')->andReturn('fixed');
        $getMock->shouldReceive('__invoke')->with('discount_value')->andReturn(0);
        $getMock->shouldReceive('__invoke')->with('payment_method')->andReturn('credit');
        $getMock->shouldReceive('__invoke')->with('paid_amount')->andReturn(0);

        $this->invokeRecalculateTotals($setMock, $getMock);
    }

    public function test_credit_payment_partial()
    {
        // Scenario: Total 1000, Paid 200
        $items = ['1' => ['total' => 1000]];

        $setMock = Mockery::mock(Set::class);
        $setMock->shouldReceive('__invoke')->with('subtotal', 1000)->once();
        $setMock->shouldReceive('__invoke')->with('discount', 0)->once();
        $setMock->shouldReceive('__invoke')->with('total', 1000)->once();
        // Remaining should be 800
        $setMock->shouldReceive('__invoke')->with('remaining_amount', 800.0)->once();

        $getMock = Mockery::mock(Get::class);
        $getMock->shouldReceive('__invoke')->with('items')->andReturn($items);
        $getMock->shouldReceive('__invoke')->with('discount_type')->andReturn('fixed');
        $getMock->shouldReceive('__invoke')->with('discount_value')->andReturn(0);
        $getMock->shouldReceive('__invoke')->with('payment_method')->andReturn('credit');
        $getMock->shouldReceive('__invoke')->with('paid_amount')->andReturn(200);

        $this->invokeRecalculateTotals($setMock, $getMock);
    }

    public function test_credit_payment_overpayment_resets()
    {
        // Scenario: Total 1000, User mistakenly entered 1200 paid
        $items = ['1' => ['total' => 1000]];

        $setMock = Mockery::mock(Set::class);
        $setMock->shouldReceive('__invoke')->with('subtotal', 1000)->once();
        $setMock->shouldReceive('__invoke')->with('discount', 0)->once();
        $setMock->shouldReceive('__invoke')->with('total', 1000)->once();

        // Overpayment logic: Reset paid to 0, remaining to full total
        $setMock->shouldReceive('__invoke')->with('paid_amount', 0)->once();
        $setMock->shouldReceive('__invoke')->with('remaining_amount', 1000)->once();

        $getMock = Mockery::mock(Get::class);
        $getMock->shouldReceive('__invoke')->with('items')->andReturn($items);
        $getMock->shouldReceive('__invoke')->with('discount_type')->andReturn('fixed');
        $getMock->shouldReceive('__invoke')->with('discount_value')->andReturn(0);
        $getMock->shouldReceive('__invoke')->with('payment_method')->andReturn('credit');
        $getMock->shouldReceive('__invoke')->with('paid_amount')->andReturn(1200);

        $this->invokeRecalculateTotals($setMock, $getMock);
    }

    public function test_decimal_precision()
    {
        // Scenario: 3 items with 3 decimal places
        // Note: PHP floating point math is tricky. 10.333 + 20.333 + 30.333 might not be exactly 60.999
        // However, collect()->sum() works reasonably well for simple sums.

        $items = [
            '1' => ['total' => 10.333],
            '2' => ['total' => 20.333],
            '3' => ['total' => 30.333],
        ];

        $expectedSum = 60.999;

        $setMock = Mockery::mock(Set::class);
        // Use Mockery::on to allow for slight floating point variations or exact matches
        // But for 10.333 + 20.333 + 30.333 it should be 60.999 exactly if handled as floats
        // The error suggests it didn't match.
        // Let's debug by printing or just relaxing the check to verify logic flow

        $setMock->shouldReceive('__invoke')
            ->with('subtotal', Mockery::on(fn ($val) => abs($val - $expectedSum) < 0.00001))
            ->once();

        $setMock->shouldReceive('__invoke')->with('discount', 0)->once();

        $setMock->shouldReceive('__invoke')
            ->with('total', Mockery::on(fn ($val) => abs($val - $expectedSum) < 0.00001))
            ->once();

        $setMock->shouldReceive('__invoke')
            ->with('paid_amount', Mockery::on(fn ($val) => abs($val - $expectedSum) < 0.00001))
            ->once();

        $setMock->shouldReceive('__invoke')->with('remaining_amount', 0)->once();

        $getMock = Mockery::mock(Get::class);
        $getMock->shouldReceive('__invoke')->with('items')->andReturn($items);
        $getMock->shouldReceive('__invoke')->with('discount_type')->andReturn('fixed');
        $getMock->shouldReceive('__invoke')->with('discount_value')->andReturn(0);
        $getMock->shouldReceive('__invoke')->with('payment_method')->andReturn('cash');
        $getMock->shouldReceive('__invoke')->with('paid_amount')->andReturn(0);

        $this->invokeRecalculateTotals($setMock, $getMock);
    }
}
