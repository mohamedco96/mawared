<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PurchaseInvoice extends Model
{
    use HasFactory, HasUlids, SoftDeletes, LogsActivity;

    protected $fillable = [
        'invoice_number',
        'warehouse_id',
        'partner_id',
        'status',
        'payment_method',
        'discount_type',
        'discount_value',
        'subtotal',
        'discount',
        'total',
        'paid_amount',
        'remaining_amount',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:4',
            'discount' => 'decimal:4',
            'discount_value' => 'decimal:4',
            'total' => 'decimal:4',
            'paid_amount' => 'decimal:4',
            'remaining_amount' => 'decimal:4',
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
        return $this->hasMany(PurchaseInvoiceItem::class);
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

    public function payments(): MorphMany
    {
        return $this->morphMany(InvoicePayment::class, 'payable');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class, 'purchase_invoice_id');
    }

    // Helper Methods

    /**
     * Get total amount paid on this invoice (initial + subsequent payments)
     */
    public function getTotalPaidAttribute(): float
    {
        // Check if payments relationship is already loaded to avoid N+1
        if (!$this->relationLoaded('payments')) {
            $this->loadSum('payments', 'amount');
        }
        return floatval($this->paid_amount) + ($this->payments_sum_amount ?? 0);
    }

    /**
     * Get current remaining balance on this invoice
     */
    public function getCurrentRemainingAttribute(): float
    {
        // Load returns sum if not already loaded
        if (!$this->relationLoaded('returns')) {
            $this->loadSum('returns', 'total');
        }

        $returnsTotal = floatval($this->returns_sum_total ?? 0);
        $netTotal = floatval($this->total) - $returnsTotal;

        return $netTotal - $this->total_paid;
    }
    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Calculate the actual discount amount based on type
     */
    public function getCalculatedDiscountAttribute(): float
    {
        if ($this->discount_type === 'percentage') {
            return $this->subtotal * ($this->discount_value / 100);
        }
        return (float) $this->discount_value;
    }

    /**
     * Calculate net total (subtotal - calculated discount)
     */
    public function getNetTotalAttribute(): float
    {
        return $this->subtotal - $this->calculated_discount;
    }

    /**
     * Check if invoice is fully paid
     */
    public function isFullyPaid(): bool
    {
        return bccomp((string) $this->remaining_amount, '0', 4) === 0;
    }

    /**
     * Check if invoice is partially paid
     */
    public function isPartiallyPaid(): bool
    {
        return bccomp((string) $this->paid_amount, '0', 4) === 1
            && bccomp((string) $this->remaining_amount, '0', 4) === 1;
    }

    /**
     * Check if invoice is fully returned
     */
    public function isFullyReturned(): bool
    {
        // Load returns sum if not already loaded
        if (!$this->relationLoaded('returns')) {
            $this->loadSum('returns', 'total');
        }

        $returnsTotal = floatval($this->returns_sum_total ?? 0);

        // Compare using bccomp for precision
        return bccomp((string) $returnsTotal, (string) $this->total, 4) >= 0;
    }

    /**
     * Get total quantity returned for a specific product and unit type
     */
    public function getReturnedQuantity(string $productId, string $unitType): int
    {
        return $this->returns()
            ->where('status', 'posted')
            ->with('items')
            ->get()
            ->flatMap(function ($return) {
                return $return->items;
            })
            ->where('product_id', $productId)
            ->where('unit_type', $unitType)
            ->sum('quantity');
    }

    /**
     * Get available quantity for return for a specific product and unit type
     */
    public function getAvailableReturnQuantity(string $productId, string $unitType): int
    {
        $originalItem = $this->items()
            ->where('product_id', $productId)
            ->where('unit_type', $unitType)
            ->first();

        if (!$originalItem) {
            return 0;
        }

        $returnedQty = $this->getReturnedQuantity($productId, $unitType);
        return max(0, $originalItem->quantity - $returnedQty);
    }

    /**
     * Check if this invoice has any associated financial records that prevent deletion
     */
    public function hasAssociatedRecords(): bool
    {
        return $this->stockMovements()->exists()
            || $this->treasuryTransactions()->exists()
            || $this->payments()->exists()
            || $this->isPosted();
    }

    // Immutable Logic: Prevent updates/deletes when posted
    protected static function booted(): void
    {
        static::updating(function (PurchaseInvoice $invoice) {
            // Get the original status before changes
            $originalStatus = $invoice->getOriginal('status');

            // Allow payment-related updates to posted invoices (paid_amount, remaining_amount, status)
            $allowedFieldsForPosted = ['paid_amount', 'remaining_amount', 'status', 'updated_at'];
            $dirtyFields = array_keys($invoice->getDirty());
            $hasDisallowedChanges = !empty(array_diff($dirtyFields, $allowedFieldsForPosted));

            // If already posted, prevent updates to fields other than payment-related
            if ($originalStatus === 'posted' && $hasDisallowedChanges) {
                throw new \Exception('Cannot update a posted invoice');
            }
        });

        static::deleting(function (PurchaseInvoice $invoice) {
            if ($invoice->hasAssociatedRecords()) {
                throw new \Exception('لا يمكن حذف الفاتورة لوجود حركات مخزون أو خزينة أو مدفوعات مرتبطة بها أو لأنها مؤكدة. استخدم المرتجعات بدلاً من ذلك.');
            }
        });
    }

    // Global Search Implementation
    public function getGlobalSearchResultTitle(): string
    {
        return $this->invoice_number . ' - ' . ($this->partner?->name ?? 'N/A');
    }

    public function getGlobalSearchResultDetails(): array
    {
        return [
            'المورد' => $this->partner?->name ?? 'N/A',
            'الإجمالي' => number_format($this->total, 2) . ' ج.م',
            'الحالة' => $this->status === 'posted' ? 'مؤكدة' : 'مسودة',
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['invoice_number', 'partner.name'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => match($eventName) {
                'created' => 'تم إنشاء فاتورة مشتريات',
                'updated' => 'تم تحديث فاتورة مشتريات',
                'deleted' => 'تم حذف فاتورة مشتريات',
                default => "فاتورة مشتريات {$eventName}",
            });
    }
}
