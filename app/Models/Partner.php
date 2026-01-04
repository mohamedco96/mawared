<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Partner extends Model
{
    use HasFactory, HasUlids, SoftDeletes, LogsActivity;

    protected $fillable = [
        'legacy_id',
        'name',
        'phone',
        'type',
        'gov_id',
        'region',
        'address',
        'is_banned',
        'current_balance',
        'opening_balance',
    ];

    protected function casts(): array
    {
        return [
            'is_banned' => 'boolean',
            'current_balance' => 'decimal:4',
            'opening_balance' => 'decimal:4',
        ];
    }

    // Relationships
    public function salesInvoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class);
    }

    public function purchaseInvoices(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class);
    }

    public function treasuryTransactions(): HasMany
    {
        return $this->hasMany(TreasuryTransaction::class);
    }

    public function salesReturns(): HasMany
    {
        return $this->hasMany(SalesReturn::class);
    }

    public function purchaseReturns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class);
    }

    public function invoicePayments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    // Balance Calculation Methods

    /**
     * Calculate partner balance from invoices, returns, and payments
     * This is the SOURCE OF TRUTH for partner balances
     */
    public function calculateBalance(): float
    {
        // Get opening balance (default to 0 if not set)
        $openingBalance = floatval($this->opening_balance ?? 0);

        if ($this->type === 'customer') {
            // Customer owes us money (positive balance means they owe us)

            // What they bought on credit (only the unpaid portion - remaining_amount)
            // Cash invoices have remaining_amount = 0, so they don't affect balance
            $salesTotal = $this->salesInvoices()
                ->where('status', 'posted')
                ->sum('remaining_amount');

            // What they returned (CREDIT returns only - cash returns don't reduce debt)
            $returnsTotal = $this->salesReturns()
                ->where('status', 'posted')
                ->where('payment_method', 'credit')
                ->sum('total');

            // What they paid via subsequent payments (collection transactions)
            // Only count financial transactions (not initial invoice payments which are already in paid_amount)
            $collections = $this->treasuryTransactions()
                ->where('type', 'collection')
                ->where('reference_type', 'financial_transaction')
                ->sum('amount');

            // What we paid them back (payment transactions - negative in DB)
            // This happens when customer overpaid and we refund them
            $payments = $this->treasuryTransactions()
                ->where('type', 'payment')
                ->where('reference_type', 'financial_transaction')
                ->sum('amount'); // Already negative

            // Settlement discounts given during payments (amount + discount = total debt reduction)
            $discounts = $this->invoicePayments()
                ->sum('discount');

            // IMPORTANT: Cash refunds do NOT affect customer balance
            // When a customer returns items for CASH, they take money from treasury but their debt stays the same
            // Only CREDIT returns (captured in $returnsTotal) reduce customer debt

            // Formula explanation for customers (positive balance = they owe us):
            // Start with: Opening Balance + What they bought (Sales)
            // Subtract: What they returned (Returns) + What they paid (Collections) + Discounts
            // Add back: What we refunded to them (abs of negative Payments)
            // Since Payments is negative, we subtract it to add back the absolute value

            return $openingBalance + $salesTotal - $returnsTotal - $collections - $discounts - $payments;

        } elseif ($this->type === 'supplier') {
            // Supplier is owed money by us (negative balance means we owe them)

            // What we bought on credit (only the unpaid portion - remaining_amount)
            // Cash invoices have remaining_amount = 0, so they don't affect balance
            $purchaseTotal = $this->purchaseInvoices()
                ->where('status', 'posted')
                ->sum('remaining_amount');

            // What we returned (CREDIT returns only - cash returns don't reduce debt)
            $returnsTotal = $this->purchaseReturns()
                ->where('status', 'posted')
                ->where('payment_method', 'credit')
                ->sum('total');

            // What we paid them via subsequent payments (payment transactions - already negative in DB)
            // Only count financial transactions (not initial invoice payments which are already in paid_amount)
            $payments = $this->treasuryTransactions()
                ->where('type', 'payment')
                ->where('reference_type', 'financial_transaction')
                ->sum('amount'); // Already negative

            // What they paid us back (collection transactions - positive in DB)
            // This happens when supplier refunds us or pays us back for overpayments
            $collections = $this->treasuryTransactions()
                ->where('type', 'collection')
                ->where('reference_type', 'financial_transaction')
                ->sum('amount'); // Positive

            // Settlement discounts received during payments (amount + discount = total debt reduction)
            $discounts = $this->invoicePayments()
                ->sum('discount');

            // IMPORTANT: Cash refunds do NOT affect supplier balance
            // When we return items for CASH, supplier gives us money but our debt stays the same
            // Only CREDIT returns (captured in $returnsTotal) reduce what we owe them

            // Formula explanation for suppliers (negative balance = we owe them):
            // purchaseTotal uses remaining_amount which ALREADY INCLUDES settlement discounts
            // (When payment is made, remaining_amount is reduced by amount + discount)
            // Collections from supplier (rare - they pay us back) reduce our debt
            // Credit returns reduce our debt

            return $openingBalance - $purchaseTotal + $returnsTotal - $collections;

        } else { // shareholder
            // Shareholders track capital deposits, drawings, etc. via treasury transactions only
            return $openingBalance + $this->treasuryTransactions()->sum('amount');
        }
    }

    /**
     * Recalculate and update the current_balance field
     */
    public function recalculateBalance(): void
    {
        $this->update(['current_balance' => $this->calculateBalance()]);
    }

    /**
     * Get the formatted balance for display
     */
    public function getFormattedBalanceAttribute(): string
    {
        $balance = $this->current_balance;

        if ($this->type === 'customer') {
            return $balance >= 0
                ? 'له ' . number_format(abs($balance), 2)
                : 'عليه ' . number_format(abs($balance), 2);
        } else {
            return $balance <= 0
                ? 'له ' . number_format(abs($balance), 2)
                : 'عليه ' . number_format(abs($balance), 2);
        }
    }

    // Scopes
    public function scopeCustomers($query)
    {
        return $query->where('type', 'customer');
    }

    public function scopeSuppliers($query)
    {
        return $query->where('type', 'supplier');
    }

    public function scopeShareholders($query)
    {
        return $query->where('type', 'shareholder');
    }

    public function scopeUnknown($query)
    {
        return $query->where('type', 'unknown');
    }

    // Global Search Implementation
    public function getGlobalSearchResultTitle(): string
    {
        return $this->name;
    }

    public function getGlobalSearchResultDetails(): array
    {
        $typeLabel = match($this->type) {
            'customer' => 'عميل',
            'supplier' => 'مورد',
            'shareholder' => 'شريك (مساهم)',
            default => $this->type,
        };

        return [
            'النوع' => $typeLabel,
            'الهاتف' => $this->phone ?? '—',
            'الرصيد' => number_format($this->current_balance, 2) . ' ج.م',
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'phone', 'gov_id'];
    }

    // Deletion Protection
    protected static function booted(): void
    {
        static::deleting(function (Partner $partner) {
            $hasInvoices = $partner->salesInvoices()->exists()
                || $partner->purchaseInvoices()->exists();
            $hasReturns = $partner->salesReturns()->exists()
                || $partner->purchaseReturns()->exists();
            $hasTransactions = $partner->treasuryTransactions()->exists();
            $hasPayments = $partner->invoicePayments()->exists();

            if ($hasInvoices || $hasReturns || $hasTransactions || $hasPayments) {
                throw new \Exception('لا يمكن حذف الشريك لوجود فواتير أو معاملات مالية مرتبطة به');
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => match($eventName) {
                'created' => 'تم إنشاء شريك',
                'updated' => 'تم تحديث شريك',
                'deleted' => 'تم حذف شريك',
                default => "الشريك {$eventName}",
            });
    }
}
