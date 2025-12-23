<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'name',
        'barcode',
        'sku',
        'min_stock',
        'avg_cost',
        'small_unit_id',
        'large_unit_id',
        'factor',
        'retail_price',
        'wholesale_price',
        'large_retail_price',
        'large_wholesale_price',
    ];

    protected function casts(): array
    {
        return [
            'min_stock' => 'integer',
            'avg_cost' => 'decimal:4',
            'factor' => 'integer',
            'retail_price' => 'decimal:4',
            'wholesale_price' => 'decimal:4',
            'large_retail_price' => 'decimal:4',
            'large_wholesale_price' => 'decimal:4',
        ];
    }

    // Relationships
    public function smallUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'small_unit_id');
    }

    public function largeUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'large_unit_id');
    }

    // Helper Methods
    public function convertToBaseUnit(int $quantity, string $unitType): int
    {
        if ($unitType === 'large' && $this->large_unit_id) {
            return $quantity * $this->factor;
        }
        return $quantity;
    }
}
