<?php

namespace Tests\Feature\Controllers;

use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed settings if necessary, but Spatie Settings usually persist or need mocks
        // In testing environment, they might be array based.
    }

    public function test_print_invoice_fails_for_unposted_invoice()
    {
        // ARRANGE
        $user = User::factory()->create();
        $invoice = SalesInvoice::factory()->create([
            'status' => 'draft',
        ]);

        // ACT
        $response = $this->actingAs($user)->get(route('invoices.sales.print', $invoice));

        // ASSERT
        $response->assertForbidden();
    }

    public function test_print_invoice_returns_view_for_posted_invoice()
    {
        // ARRANGE
        $user = User::factory()->create();
        $invoice = SalesInvoice::factory()->create([
            'status' => 'posted',
        ]);
        SalesInvoiceItem::factory()->create(['sales_invoice_id' => $invoice->id]);

        // ACT
        $response = $this->actingAs($user)->get(route('invoices.sales.print', $invoice));

        // ASSERT
        $response->assertOk();
        $response->assertViewIs('invoices.print');
        $response->assertViewHas('invoice');
        $response->assertViewHas('companySettings');
    }
}
