<?php

namespace Database\Seeders;

use App\Models\Partner;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class QuotationSeeder extends Seeder
{
    public function run(): void
    {
        $customers = Partner::where('type', 'customer')->get();
        $user = User::first();
        $products = Product::all();

        if ($customers->isEmpty() || !$user || $products->isEmpty()) {
            $this->command->warn('Skipping QuotationSeeder: Missing required data (customers, user, or products)');
            return;
        }

        // Create 15 quotations with various statuses
        $statuses = [
            'draft' => 3,
            'sent' => 5,
            'accepted' => 4,
            'rejected' => 2,
            'converted' => 1,
        ];

        $quotationNumber = 1;

        foreach ($statuses as $status => $count) {
            for ($i = 0; $i < $count; $i++) {
                // 70% with partner, 30% with guest
                $hasPartner = rand(1, 10) <= 7;

                $pricingType = rand(0, 1) == 0 ? 'retail' : 'wholesale';
                $discountType = rand(0, 1) == 0 ? 'percentage' : 'fixed';

                // Valid until date
                $validUntil = null;
                if (rand(0, 10) > 3) { // 70% have valid_until
                    if ($status === 'draft' || $status === 'sent') {
                        // Future dates for active quotations
                        $validUntil = now()->addDays(rand(7, 60))->toDateString();
                    } else {
                        // Mix of past and future dates for accepted/rejected
                        $validUntil = now()->addDays(rand(-30, 60))->toDateString();
                    }
                }

                $quotation = Quotation::create([
                    'quotation_number' => 'QT-' . date('Y') . '-' . str_pad($quotationNumber++, 5, '0', STR_PAD_LEFT),
                    'partner_id' => $hasPartner ? $customers->random()->id : null,
                    'guest_name' => !$hasPartner ? 'عميل ضيف ' . $i : null,
                    'guest_phone' => !$hasPartner ? '05' . rand(10000000, 99999999) : null,
                    'pricing_type' => $pricingType,
                    'status' => $status,
                    'public_token' => Str::random(32),
                    'valid_until' => $validUntil,
                    'subtotal' => 0,
                    'discount_type' => $discountType,
                    'discount_value' => 0,
                    'discount' => 0,
                    'total' => 0,
                    'notes' => rand(0, 1) == 1 ? 'عرض سعر تجريبي ' . $quotationNumber : null,
                    'internal_notes' => rand(0, 1) == 1 ? 'ملاحظات داخلية للمتابعة' : null,
                    'created_by' => $user->id,
                ]);

                // Add 2-6 random items to each quotation
                $itemCount = rand(2, 6);
                $subtotal = 0;

                for ($j = 0; $j < $itemCount; $j++) {
                    $product = $products->random();
                    $unitType = rand(0, 1) == 0 ? 'small' : 'large';
                    $quantity = rand(1, 15);

                    // Get unit price and name based on pricing type and unit type
                    if ($unitType === 'small') {
                        $unitPrice = $pricingType === 'retail' ? $product->retail_price : $product->wholesale_price;
                        $unitName = $product->smallUnit->name ?? 'قطعة';
                    } else {
                        $unitPrice = $pricingType === 'retail' ? $product->large_retail_price : $product->large_wholesale_price;
                        $unitName = $product->largeUnit->name ?? 'كرتونة';
                    }

                    $itemDiscount = rand(0, 3) == 0 ? rand(5, 50) : 0; // 25% chance of discount
                    $itemTotal = ($unitPrice * $quantity) - $itemDiscount;

                    QuotationItem::create([
                        'quotation_id' => $quotation->id,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'unit_type' => $unitType,
                        'unit_name' => $unitName,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'discount' => $itemDiscount,
                        'total' => $itemTotal,
                        'notes' => rand(0, 5) == 0 ? 'ملاحظات على المنتج' : null,
                    ]);

                    $subtotal += $itemTotal;
                }

                // Update quotation totals (skip model events for converted status to avoid validation)
                $discountValue = rand(0, 2) == 0 ? 0 : ($discountType === 'percentage' ? rand(5, 20) : rand(10, 100));
                $discountAmount = $discountType === 'percentage' ? ($subtotal * $discountValue / 100) : $discountValue;
                $total = $subtotal - $discountAmount;

                // For converted quotations, update without triggering events
                if ($status === 'converted') {
                    $quotation->subtotal = $subtotal;
                    $quotation->discount_value = $discountValue;
                    $quotation->discount = $discountAmount;
                    $quotation->total = $total;
                    $quotation->saveQuietly();
                } else {
                    $quotation->update([
                        'subtotal' => $subtotal,
                        'discount_value' => $discountValue,
                        'discount' => $discountAmount,
                        'total' => $total,
                    ]);
                }
            }
        }

        $this->command->info('Created 15 quotations with items');
    }
}
