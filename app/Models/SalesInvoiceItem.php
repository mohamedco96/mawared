<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesInvoiceItem extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'sales_invoice_id',
        'product_id',
        'unit_type',
        'quantity',
        'unit_price',
        'discount',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:4',
            'discount' => 'decimal:4',
            'total' => 'decimal:4',
        ];
    }

    // Relationships
    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Booted method for validation
    protected static function booted()
    {
        static::saving(function ($item) {
            if ($item->quantity === 0) {
                throw new \Exception('الكمية يجب أن تكون أكبر من صفر');
            }
            if ($item->quantity < 0) {
                throw new \Exception('الكمية يجب أن تكون موجبة');
            }
        });
    }

    // Helper Methods
    public function getNetUnitPriceAttribute(): float
    {
        if ($this->quantity <= 0) {
            return floatval($this->unit_price);
        }
        return floatval($this->total) / $this->quantity;
    }
}
