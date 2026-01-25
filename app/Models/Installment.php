<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Installment extends Model
{
    use HasFactory, LogsActivity;

    /**
     * Mass assignment protection
     */
    protected $fillable = [
        'sales_invoice_id',
        'installment_number',
        'amount',
        'due_date',
        'status',
        'paid_amount',
        'invoice_payment_id',
        'paid_at',
        'paid_by',
        'notes',
    ];

    /**
     * Add casts for proper data types
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'amount' => 'decimal:4',
            'paid_amount' => 'decimal:4',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Check if this installment has any associated financial records that prevent deletion
     */
    public function hasAssociatedRecords(): bool
    {
        return $this->paid_amount > 0 || $this->status === 'paid' || $this->invoice_payment_id !== null;
    }

    /**
     * Prevent modification of critical fields after creation
     */
    protected static function booted(): void
    {
        static::updating(function (Installment $installment) {
            $immutableFields = ['sales_invoice_id', 'installment_number', 'amount', 'due_date'];

            foreach ($immutableFields as $field) {
                if ($installment->isDirty($field)) {
                    throw new \Exception("لا يمكن تعديل حقل {$field} بعد إنشاء القسط");
                }
            }
        });

        static::deleting(function (Installment $installment) {
            if ($installment->hasAssociatedRecords()) {
                throw new \Exception('لا يمكن حذف قسط تم دفع مبلغ منه');
            }

            throw new \Exception('لا يمكن حذف سجلات الأقساط. الأقساط مرتبطة بالفواتير ولا يمكن حذفها بشكل فردي.');
        });
    }

    /**
     * Activity logging configuration
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'paid_amount', 'invoice_payment_id', 'notes'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => match($eventName) {
                'created' => 'تم إنشاء قسط',
                'updated' => 'تم تحديث قسط',
                'deleted' => 'تم حذف قسط',
                default => "قسط {$eventName}",
            });
    }

    /**
     * Real-time status accessor for immediate overdue detection
     */
    public function getStatusAttribute($value): string
    {
        // If marked as paid, always return paid
        if ($value === 'paid') {
            return 'paid';
        }

        // Real-time check for overdue
        if ($this->attributes['due_date'] < now()->format('Y-m-d') && $value === 'pending') {
            return 'overdue';
        }

        return $value;
    }

    /**
     * Get the sales invoice this installment belongs to
     */
    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    /**
     * Get the payment that paid this installment (if paid)
     */
    public function invoicePayment(): BelongsTo
    {
        return $this->belongsTo(InvoicePayment::class);
    }

    /**
     * Get the user who marked this installment as paid
     */
    public function paidByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /**
     * Helper: Check if installment is fully paid
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Helper: Check if installment is overdue
     */
    public function isOverdue(): bool
    {
        return $this->status === 'overdue';
    }

    /**
     * Helper: Get remaining amount to be paid
     */
    public function getRemainingAmountAttribute(): string
    {
        return bcsub($this->amount, $this->paid_amount, 4);
    }
}
