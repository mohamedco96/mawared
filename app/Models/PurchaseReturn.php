<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReturn extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'return_number',
        'warehouse_id',
        'partner_id',
        'status',
        'payment_method',
        'subtotal',
        'discount',
        'total',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:4',
            'discount' => 'decimal:4',
            'total' => 'decimal:4',
        ];
    }

    // Relationships
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }

    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }

    public function treasuryTransactions(): MorphMany
    {
        return $this->morphMany(TreasuryTransaction::class, 'reference');
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
        static::updating(function (PurchaseReturn $return) {
            if ($return->isPosted() && $return->isDirty()) {
                throw new \Exception('Cannot update a posted return');
            }
        });

        static::deleting(function (PurchaseReturn $return) {
            if ($return->isPosted()) {
                throw new \Exception('Cannot delete a posted return');
            }
        });
    }
}
