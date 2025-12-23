<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesInvoice extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'invoice_number',
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
        return $this->hasMany(SalesInvoiceItem::class);
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
        static::updating(function (SalesInvoice $invoice) {
            if ($invoice->isPosted() && $invoice->isDirty()) {
                throw new \Exception('Cannot update a posted invoice');
            }
        });

        static::deleting(function (SalesInvoice $invoice) {
            if ($invoice->isPosted()) {
                throw new \Exception('Cannot delete a posted invoice');
            }
        });
    }
}
