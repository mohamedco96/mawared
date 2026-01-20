<?php

namespace Tests\Feature\Services\InstallmentService;

use App\Models\Installment;
use App\Models\Partner;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InstallmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurchargeTest extends TestCase
{
    use RefreshDatabase;

    protected InstallmentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InstallmentService::class);
        $this->actingAs(User::factory()->create());
    }

    /** @test */
    public function it_calculates_and_applies_surcharge_correctly()
    {
        $invoice = SalesInvoice::factory()->create([
            'status' => 'posted',
            'payment_method' => 'credit',
            'total' => '1000.0000',
            'remaining_amount' => '1000.0000',
            'has_installment_plan' => true,
            'installment_months' => 10,
            'installment_start_date' => now()->format('Y-m-d'),
            'installment_interest_percentage' => '10.00', // 10% surcharge
        ]);

        $this->service->generateInstallmentSchedule($invoice);

        $invoice->refresh();

        // 10% of 1000 = 100
        $this->assertEquals('100.0000', $invoice->installment_interest_amount);
        
        // Total should be 1100
        $this->assertEquals('1100.0000', $invoice->total);
        $this->assertEquals('1100.0000', $invoice->remaining_amount);
    }

    /** @test */
    public function it_distributes_total_with_surcharge_across_installments()
    {
        $invoice = SalesInvoice::factory()->create([
            'status' => 'posted',
            'payment_method' => 'credit',
            'total' => '1000.0000',
            'remaining_amount' => '1000.0000',
            'has_installment_plan' => true,
            'installment_months' => 5,
            'installment_start_date' => now()->format('Y-m-d'),
            'installment_interest_percentage' => '10.00', // 10% surcharge
        ]);

        $this->service->generateInstallmentSchedule($invoice);

        // New total = 1100. Installment = 1100 / 5 = 220
        $installments = $invoice->installments()->orderBy('installment_number')->get();

        $this->assertCount(5, $installments);
        foreach ($installments as $installment) {
            $this->assertEquals('220.0000', $installment->amount);
        }

        $this->assertEquals('1100.0000', $installments->sum('amount'));
    }

    /** @test */
    public function it_handles_zero_surcharge_correctly()
    {
        $invoice = SalesInvoice::factory()->create([
            'status' => 'posted',
            'payment_method' => 'credit',
            'total' => '1000.0000',
            'remaining_amount' => '1000.0000',
            'has_installment_plan' => true,
            'installment_months' => 5,
            'installment_interest_percentage' => '0.00',
        ]);

        $this->service->generateInstallmentSchedule($invoice);

        $invoice->refresh();

        $this->assertEquals('0.0000', $invoice->installment_interest_amount);
        $this->assertEquals('1000.0000', $invoice->total);
        
        $installments = $invoice->installments;
        $this->assertEquals('200.0000', $installments[0]->amount);
        $this->assertEquals('1000.0000', $installments->sum('amount'));
    }
}
