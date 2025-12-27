<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Partner extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'type',
        'gov_id',
        'region',
        'is_banned',
        'current_balance',
    ];

    protected function casts(): array
    {
        return [
            'is_banned' => 'boolean',
            'current_balance' => 'decimal:2',
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
        if ($this->type === 'customer') {
            // Customer owes us money (positive balance means they owe us)

            // What they bought
            $salesTotal = $this->salesInvoices()
                ->where('status', 'posted')
                ->sum('total');

            // What they returned
            $returnsTotal = $this->salesReturns()
                ->where('status', 'posted')
                ->sum('total');

            // What they paid (collection transactions from posted invoices + financial transactions)
            $collections = $this->treasuryTransactions()
                ->where('type', 'collection')
                ->where(function ($q) {
                    $q->whereIn('reference_type', ['sales_invoice', 'financial_transaction'])
                      ->orWhereNull('reference_type');
                })
                ->sum('amount');

            // Cash refunds we gave them (negative in DB)
            $refunds = $this->treasuryTransactions()
                ->where('type', 'refund')
                ->whereIn('reference_type', ['sales_return', 'financial_transaction'])
                ->sum('amount'); // Already negative

            return $salesTotal - $returnsTotal - $collections + abs($refunds);

        } elseif ($this->type === 'supplier') {
            // Supplier is owed money by us (negative balance means we owe them)

            // What we bought
            $purchaseTotal = $this->purchaseInvoices()
                ->where('status', 'posted')
                ->sum('total');

            // What we returned
            $returnsTotal = $this->purchaseReturns()
                ->where('status', 'posted')
                ->sum('total');

            // What we paid them (payment transactions - already negative in DB)
            $payments = $this->treasuryTransactions()
                ->where('type', 'payment')
                ->where(function ($q) {
                    $q->whereIn('reference_type', ['purchase_invoice', 'financial_transaction'])
                      ->orWhereNull('reference_type');
                })
                ->sum('amount'); // Already negative

            // Cash refunds they gave us (positive in DB)
            $refunds = $this->treasuryTransactions()
                ->where('type', 'refund')
                ->whereIn('reference_type', ['purchase_return', 'financial_transaction'])
                ->sum('amount'); // Already positive

            // Collections from supplier (when we collect money back, e.g., for credit returns)
            // These are positive amounts that reduce what we owe them
            $collections = $this->treasuryTransactions()
                ->where('type', 'collection')
                ->where(function ($q) {
                    $q->whereIn('reference_type', ['purchase_return', 'financial_transaction'])
                      ->orWhereNull('reference_type');
                })
                ->sum('amount'); // Already positive

            // Return negative value (we owe them)
            // Note: refunds and collections are ADDED because they reduce what we owe (positive values reduce debt)
            return -1 * ($purchaseTotal - $returnsTotal + $payments + $refunds + $collections);

        } else { // shareholder
            // Shareholders track capital deposits, drawings, etc. via treasury transactions only
            return $this->treasuryTransactions()->sum('amount');
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
}
