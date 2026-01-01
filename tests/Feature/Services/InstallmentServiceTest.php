<?php

namespace Tests\Feature\Services;

use PHPUnit\Framework\Attributes\Test;
use App\Models\Installment;
use App\Models\InvoicePayment;
use App\Models\Partner;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InstallmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallmentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected InstallmentService $installmentService;
    protected User $user;
    protected Warehouse $warehouse;
    protected Partner $partner;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installmentService = new InstallmentService();
        $this->user = User::factory()->create();
        $this->warehouse = Warehouse::factory()->create();
        $this->partner = Partner::factory()->customer()->create();
        $this->product = Product::factory()->create([
            'avg_cost' => '50.00',
            'retail_price' => '100.00',
        ]);

        // Create stock for testing
        StockMovement::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'type' => 'purchase',
            'quantity' => 1000,
            'cost_at_time' => '50.00',
            'reference_type' => 'test_setup',
            'reference_id' => 'setup-' . now()->timestamp,
        ]);

        $this->actingAs($this->user);
    }

    /**
     * SCHEDULE GENERATION TESTS
     */

        #[Test]
    public function test_generates_correct_installment_amounts_for_even_division(): void
    {
        $invoice = SalesInvoice::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'partner_id' => $this->partner->id,
            'total' => '1200.0000',
            'paid_amount' => '0.0000',
            'remaining_amount' => '1200.0000',
            'status' => 'posted',
            'has_installment_plan' => true,
            'installment_months' => 4,
            'installment_start_date' => '2026-01-15',
        ]);

        $this->installmentService->generateInstallmentSchedule($invoice);

        $installments = $invoice->installments()->orderBy('installment_number')->get();

        $this->assertCount(4, $installments);
        $this->assertEquals('300.0000', $installments[0]->amount);
        $this->assertEquals('300.0000', $installments[1]->amount);
        $this->assertEquals('300.0000', $installments[2]->amount);
        $this->assertEquals('300.0000', $installments[3]->amount);

        // Verify sum equals total
        $sum = $installments->sum('amount');
        $this->assertEquals('1200.0000', $sum);
    }

        #[Test]
    public function test_handles_rounding_by_adjusting_last_installment(): void
    {
        $invoice = SalesInvoice::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'partner_id' => $this->partner->id,
            'total' => '1000.0000',
            'remaining_amount' => '1000.0000',
            'status' => 'posted',
            'has_installment_plan' => true,
            'installment_months' => 3,
            'installment_start_date' => '2026-01-15',
        ]);

        $this->installmentService->generateInstallmentSchedule($invoice);

        $installments = $invoice->installments()->orderBy('installment_number')->get();

        $this->assertEquals('333.3333', $installments[0]->amount);
        $this->assertEquals('333.3333', $installments[1]->amount);
        $this->assertEquals('333.3334', $installments[2]->amount); // Absorbs rounding difference

        // Verify sum equals exactly 1000
        $sum = bcadd(bcadd($installments[0]->amount, $installments[1]->amount, 4), $installments[2]->amount, 4);
        $this->assertEquals('1000.0000', $sum);
    }

        #[Test]
    public function test_prevents_schedule_generation_for_draft_invoice(): void
    {
        $invoice = SalesInvoice::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'partner_id' => $this->partner->id,
            'status' => 'draft',
            'has_installment_plan' => true,
            'installment_months' => 3,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('الفاتورة يجب أن تكون مرحّلة');

        $this->installmentService->generateInstallmentSchedule($invoice);
    }

        #[Test]
    public function test_prevents_duplicate_schedule_generation(): void
    {
        $invoice = SalesInvoice::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'partner_id' => $this->partner->id,
            'status' => 'posted',
            'total' => '1000.0000',
            'remaining_amount' => '1000.0000', // Ensure there's remaining amount
            'has_installment_plan' => true,
            'installment_months' => 3,
            'installment_start_date' => now(),
        ]);

        // Create an existing installment
        Installment::factory()->create([
            'sales_invoice_id' => $invoice->id,
            'installment_number' => 1,
            'amount' => '100.0000',
            'due_date' => now()->addMonth(),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('خطة الأقساط موجودة بالفعل');

        $this->installmentService->generateInstallmentSchedule($invoice);
    }

        #[Test]
    public function test_calculates_correct_due_dates(): void
    {
        $invoice = SalesInvoice::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'partner_id' => $this->partner->id,
            'total' => '900.0000',
            'remaining_amount' => '900.0000',
            'status' => 'posted',
            'has_installment_plan' => true,
            'installment_months' => 3,
            'installment_start_date' => '2026-01-15',
        ]);

        $this->installmentService->generateInstallmentSchedule($invoice);

        $installments = $invoice->installments()->orderBy('installment_number')->get();

        $this->assertEquals('2026-01-15', $installments[0]->due_date->format('Y-m-d'));
        $this->assertEquals('2026-02-15', $installments[1]->due_date->format('Y-m-d'));
        $this->assertEquals('2026-03-15', $installments[2]->due_date->format('Y-m-d'));
    }

    /**
     * FIFO PAYMENT APPLICATION TESTS
     */

        #[Test]
    public function test_applies_payment_to_oldest_installment_first(): void
    {
        $invoice = SalesInvoice::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'partner_id' => $this->partner->id,
            'total' => '1000.0000',
            'remaining_amount' => '1000.0000',
            'status' => 'posted',
        ]);

        // Create installments manually for precise control
        Installment::create([
            'sales_invoice_id' => $invoice->id,
            'installment_number' => 1,
            'amount' => '300.0000',
            'due_date' => '2026-01-15',
            'status' => 'pending',
            'paid_amount' => '0.0000',
        ]);

        Installment::create([
            'sales_invoice_id' => $invoice->id,
            'installment_number' => 2,
            'amount' => '300.0000',
            'due_date' => '2026-02-15',
            'status' => 'pending',
            'paid_amount' => '0.0000',
        ]);

        Installment::create([
            'sales_invoice_id' => $invoice->id,
            'installment_number' => 3,
            'amount' => '400.0000',
            'due_date' => '2026-03-15',
            'status' => 'pending',
            'paid_amount' => '0.0000',
        ]);

        // Create payment of 500
        $payment = InvoicePayment::create([
            'payable_type' => 'sales_invoice',
            'payable_id' => $invoice->id,
            'partner_id' => $this->partner->id,
            'amount' => '500.0000',
            'payment_date' => now(),
        ]);

        $this->installmentService->applyPaymentToInstallments($invoice, $payment);

        $installments = $invoice->installments()->orderBy('due_date')->get();

        // First installment: FULLY PAID
        $this->assertEquals('paid', $installments[0]->status);
        $this->assertEquals('300.0000', $installments[0]->paid_amount);

        // Second installment: PARTIALLY PAID (200 out of 300)
        // Note: Due date is in past, so accessor returns 'overdue' even though DB has 'pending'
        $this->assertContains($installments[1]->status, ['pending', 'overdue']);
        $this->assertEquals('200.0000', $installments[1]->paid_amount);

        // Third installment: UNTOUCHED
        $this->assertContains($installments[2]->status, ['pending', 'overdue']);
        $this->assertEquals('0.0000', $installments[2]->paid_amount);
    }

        #[Test]
    public function test_prevents_overpayment_of_individual_installment(): void
    {
        $invoice = SalesInvoice::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'partner_id' => $this->partner->id,
            'total' => '300.0000',
            'status' => 'posted',
        ]);

        Installment::create([
            'sales_invoice_id' => $invoice->id,
            'installment_number' => 1,
            'amount' => '300.0000',
            'due_date' => '2026-01-15',
            'status' => 'pending',
            'paid_amount' => '0.0000',
        ]);

        // Payment exceeds installment amount
        $payment = InvoicePayment::create([
            'payable_type' => 'sales_invoice',
            'payable_id' => $invoice->id,
            'partner_id' => $this->partner->id,
            'amount' => '500.0000',
            'payment_date' => now(),
        ]);

        $this->installmentService->applyPaymentToInstallments($invoice, $payment);

        $installment = $invoice->installments()->first();

        // Should only apply 300, not 500
        $this->assertEquals('300.0000', $installment->paid_amount);
        $this->assertEquals('paid', $installment->status);

        // Check for overpayment warning in activity log
        $this->assertDatabaseHas('activity_log', [
            'description' => 'تحذير: دفعة تزيد عن إجمالي الأقساط المتبقية',
        ]);
    }

        #[Test]
    public function test_marks_installment_as_paid_when_fully_settled(): void
    {
        $invoice = SalesInvoice::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'partner_id' => $this->partner->id,
            'total' => '300.0000',
            'status' => 'posted',
        ]);

        $installment = Installment::create([
            'sales_invoice_id' => $invoice->id,
            'installment_number' => 1,
            'amount' => '300.0000',
            'due_date' => '2026-01-15',
            'status' => 'pending',
            'paid_amount' => '200.0000', // Already partially paid
        ]);

        // Payment completes the remaining 100
        $payment = InvoicePayment::create([
            'payable_type' => 'sales_invoice',
            'payable_id' => $invoice->id,
            'partner_id' => $this->partner->id,
            'amount' => '100.0000',
            'payment_date' => now(),
        ]);

        $this->installmentService->applyPaymentToInstallments($invoice, $payment);

        $installment->refresh();

        $this->assertEquals('paid', $installment->status);
        $this->assertEquals('300.0000', $installment->paid_amount);
        $this->assertNotNull($installment->paid_at);
        $this->assertEquals($this->user->id, $installment->paid_by);
        $this->assertEquals($payment->id, $installment->invoice_payment_id);
    }

        #[Test]
    public function test_updates_installment_with_payment_reference(): void
    {
        $invoice = SalesInvoice::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'partner_id' => $this->partner->id,
            'total' => '100.0000',
            'status' => 'posted',
        ]);

        Installment::create([
            'sales_invoice_id' => $invoice->id,
            'installment_number' => 1,
            'amount' => '100.0000',
            'due_date' => '2026-01-15',
            'status' => 'pending',
            'paid_amount' => '0.0000',
        ]);

        $payment = InvoicePayment::create([
            'payable_type' => 'sales_invoice',
            'payable_id' => $invoice->id,
            'partner_id' => $this->partner->id,
            'amount' => '100.0000',
            'payment_date' => now(),
        ]);

        $this->installmentService->applyPaymentToInstallments($invoice, $payment);

        $installment = $invoice->installments()->first();

        $this->assertEquals($payment->id, $installment->invoice_payment_id);
    }

        #[Test]
    public function test_handles_partial_payment_correctly(): void
    {
        $invoice = SalesInvoice::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'partner_id' => $this->partner->id,
            'total' => '500.0000',
            'status' => 'posted',
        ]);

        Installment::create([
            'sales_invoice_id' => $invoice->id,
            'installment_number' => 1,
            'amount' => '500.0000',
            'due_date' => '2026-01-15',
            'status' => 'pending',
            'paid_amount' => '0.0000',
        ]);

        // Partial payment
        $payment = InvoicePayment::create([
            'payable_type' => 'sales_invoice',
            'payable_id' => $invoice->id,
            'partner_id' => $this->partner->id,
            'amount' => '200.0000',
            'payment_date' => now(),
        ]);

        $this->installmentService->applyPaymentToInstallments($invoice, $payment);

        $installment = $invoice->installments()->first();

        $this->assertEquals('pending', $installment->status); // Still pending
        $this->assertEquals('200.0000', $installment->paid_amount);
        $this->assertNull($installment->paid_at); // Not fully paid yet
    }

        #[Test]
    public function test_processes_multiple_installments_in_single_payment(): void
    {
        $invoice = SalesInvoice::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'partner_id' => $this->partner->id,
            'total' => '300.0000',
            'status' => 'posted',
        ]);

        Installment::create([
            'sales_invoice_id' => $invoice->id,
            'installment_number' => 1,
            'amount' => '100.0000',
            'due_date' => '2026-01-15',
            'status' => 'pending',
            'paid_amount' => '0.0000',
        ]);

        Installment::create([
            'sales_invoice_id' => $invoice->id,
            'installment_number' => 2,
            'amount' => '100.0000',
            'due_date' => '2026-02-15',
            'status' => 'pending',
            'paid_amount' => '0.0000',
        ]);

        Installment::create([
            'sales_invoice_id' => $invoice->id,
            'installment_number' => 3,
            'amount' => '100.0000',
            'due_date' => '2026-03-15',
            'status' => 'pending',
            'paid_amount' => '0.0000',
        ]);

        // Payment covers first 2 installments + 50 of third
        $payment = InvoicePayment::create([
            'payable_type' => 'sales_invoice',
            'payable_id' => $invoice->id,
            'partner_id' => $this->partner->id,
            'amount' => '250.0000',
            'payment_date' => now(),
        ]);

        $this->installmentService->applyPaymentToInstallments($invoice, $payment);

        $installments = $invoice->installments()->orderBy('due_date')->get();

        $this->assertEquals('paid', $installments[0]->status);
        $this->assertEquals('100.0000', $installments[0]->paid_amount);

        $this->assertEquals('paid', $installments[1]->status);
        $this->assertEquals('100.0000', $installments[1]->paid_amount);

        $this->assertEquals('pending', $installments[2]->status);
        $this->assertEquals('50.0000', $installments[2]->paid_amount);
    }

        #[Test]
    public function test_logs_overpayment_warning(): void
    {
        $invoice = SalesInvoice::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'partner_id' => $this->partner->id,
            'total' => '100.0000',
            'status' => 'posted',
        ]);

        Installment::create([
            'sales_invoice_id' => $invoice->id,
            'installment_number' => 1,
            'amount' => '100.0000',
            'due_date' => '2026-01-15',
            'status' => 'pending',
            'paid_amount' => '0.0000',
        ]);

        // Payment exceeds total remaining
        $payment = InvoicePayment::create([
            'payable_type' => 'sales_invoice',
            'payable_id' => $invoice->id,
            'partner_id' => $this->partner->id,
            'amount' => '500.0000',
            'payment_date' => now(),
        ]);

        $this->installmentService->applyPaymentToInstallments($invoice, $payment);

        // Verify warning was logged
        $this->assertDatabaseHas('activity_log', [
            'description' => 'تحذير: دفعة تزيد عن إجمالي الأقساط المتبقية',
        ]);
    }

    /**
     * OVERDUE DETECTION TESTS
     */

        #[Test]
    public function test_real_time_status_accessor_marks_overdue(): void
    {
        $installment = Installment::factory()->create([
            'due_date' => now()->subDay(),
            'status' => 'pending', // Database status
            'paid_amount' => '0.0000',
        ]);

        // Accessing status should return 'overdue' (real-time accessor)
        $this->assertEquals('overdue', $installment->status);
    }

        #[Test]
    public function test_scheduled_task_updates_overdue_installments(): void
    {
        // Create 3 overdue installments
        Installment::factory()->count(3)->create([
            'due_date' => now()->subDays(2),
            'status' => 'pending',
            'paid_amount' => '0.0000',
        ]);

        $count = $this->installmentService->updateOverdueInstallments();

        $this->assertEquals(3, $count);
        $this->assertEquals(3, Installment::where('status', 'overdue')->count());
    }

    /**
     * IMMUTABILITY TESTS
     */

        #[Test]
    public function test_prevents_modifying_amount_after_creation(): void
    {
        $installment = Installment::factory()->create([
            'amount' => '300.0000',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('لا يمكن تعديل حقل amount');

        $installment->update(['amount' => '500.0000']);
    }

        #[Test]
    public function test_prevents_modifying_due_date_after_creation(): void
    {
        $installment = Installment::factory()->create([
            'due_date' => '2026-01-15',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('لا يمكن تعديل حقل due_date');

        $installment->update(['due_date' => now()->addMonth()]);
    }

        #[Test]
    public function test_prevents_modifying_installment_number(): void
    {
        $installment = Installment::factory()->create([
            'installment_number' => 1,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('لا يمكن تعديل حقل installment_number');

        $installment->update(['installment_number' => 99]);
    }

        #[Test]
    public function test_prevents_deleting_installment_with_payment(): void
    {
        $installment = Installment::factory()->create([
            'paid_amount' => '100.0000',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('لا يمكن حذف قسط تم دفع مبلغ منه');

        $installment->delete();
    }
}
