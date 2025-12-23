<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseInvoiceItem extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'purchase_invoice_id',
        'product_id',
        'unit_type',
        'quantity',
        'unit_cost',
        'discount',
        'total',
        'new_selling_price',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'decimal:4',
            'discount' => 'decimal:4',
            'total' => 'decimal:4',
            'new_selling_price' => 'decimal:4',
        ];
    }

    // Relationships
    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
