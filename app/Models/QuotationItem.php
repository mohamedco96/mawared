<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'quotation_id',
        'product_id',
        'product_name',
        'unit_type',
        'unit_name',
        'quantity',
        'unit_price',
        'discount',
        'total',
        'notes',
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
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    // Accessors
    public function getNetUnitPriceAttribute(): float
    {
        if ($this->quantity == 0) {
            return 0;
        }

        return $this->unit_price - ($this->discount / $this->quantity);
    }

    // Methods
    public function calculateTotal(): float
    {
        return ($this->quantity * $this->unit_price) - $this->discount;
    }
}
