<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class StockAdjustment extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'warehouse_id',
        'product_id',
        'status',
        'type',
        'quantity',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    // Relationships
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Helper Methods
    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    // Immutable Logic: Prevent updates/deletes when posted
    protected static function booted(): void
    {
        static::updating(function (StockAdjustment $adjustment) {
            if ($adjustment->isPosted() && $adjustment->isDirty()) {
                throw new \Exception('Cannot update a posted stock adjustment');
            }
        });

        static::deleting(function (StockAdjustment $adjustment) {
            if ($adjustment->isPosted()) {
                throw new \Exception('Cannot delete a posted stock adjustment');
            }
        });
    }
}
