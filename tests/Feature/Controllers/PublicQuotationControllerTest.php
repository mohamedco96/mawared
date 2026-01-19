<?php

namespace Tests\Feature\Controllers;

use App\Models\Quotation;
use App\Models\QuotationItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicQuotationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_public_quotation_returns_view()
    {
        // ARRANGE
        $token = Str::random(32);
        $quotation = Quotation::factory()->create([
            'public_token' => $token,
            'status' => 'accepted', // Assuming status doesn't block view, but token does
            'valid_until' => now()->addDays(7),
        ]);
        QuotationItem::factory()->create([
            'quotation_id' => $quotation->id,
            'unit_price' => 100,
            'total' => 100,
        ]);

        // ACT
        $response = $this->get(route('quotations.public', $token));

        // ASSERT
        $response->assertOk();
        $response->assertViewIs('quotations.public-catalog');
        $response->assertViewHas('quotation');
    }

    public function test_show_public_quotation_fails_with_invalid_token()
    {
        // ACT
        $response = $this->get(route('quotations.public', 'invalid-token'));

        // ASSERT
        $response->assertNotFound();
    }

    public function test_download_pdf_returns_view()
    {
        // ARRANGE
        $token = Str::random(32);
        $quotation = Quotation::factory()->create([
            'public_token' => $token,
        ]);
        QuotationItem::factory()->create([
            'quotation_id' => $quotation->id,
            'unit_price' => 100,
            'total' => 100,
        ]);

        // ACT
        $response = $this->get(route('quotations.public.pdf', $token));

        // ASSERT
        $response->assertOk();
        $response->assertViewIs('quotations.public-pdf');
    }
}
