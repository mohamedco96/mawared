<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'current_capital',
        'equity_percentage',
        'is_manager',
        'monthly_salary',
    ];

    protected function casts(): array
    {
        return [
            'is_banned' => 'boolean',
            'current_balance' => 'decimal:4',
            'opening_balance' => 'decimal:4',
            'current_capital' => 'decimal:4',
            'equity_percentage' => 'decimal:4',
            'is_manager' => 'boolean',
            'monthly_salary' => 'decimal:4',
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

    public function equityPeriods(): BelongsToMany
    {
        return $this->belongsToMany(EquityPeriod::class, 'equity_period_partners')
            ->withPivot([
                'equity_percentage',
                'capital_at_start',
                'profit_allocated',
                'capital_injected',
                'drawings_taken',
            ])
            ->withTimestamps();
    }

    public function contributedAssets(): HasMany
    {
        return $this->hasMany(FixedAsset::class, 'contributing_partner_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

            // Discounts from financial transactions (not invoice-level discounts)
            $financialDiscounts = $this->treasuryTransactions()
                ->where('type', 'discount')
                ->where('reference_type', 'financial_transaction')
                ->sum('amount'); // Positive for collections, negative for payments

            // IMPORTANT: Cash refunds do NOT affect customer balance
            // When a customer returns items for CASH, they take money from treasury but their debt stays the same
            // Only CREDIT returns (captured in $returnsTotal) reduce customer debt

            // Formula explanation for customers (positive balance = they owe us):
            // Start with: Opening Balance + What they bought (Sales)
            // Subtract: What they returned (Returns) + What they paid (Collections) + Financial discounts
            // Add back: What we refunded to them (abs of negative Payments)
            // Since Payments is negative, we subtract it to add back the absolute value

            return $openingBalance + $salesTotal - $returnsTotal - $collections - $financialDiscounts - $payments;

        } elseif ($this->type === 'supplier') {
            // Supplier: We owe them money (positive balance means we owe them)

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

            // Financial transaction discounts (negative for payments, so adding reduces balance)
            $financialDiscounts = $this->treasuryTransactions()
                ->where('type', 'discount')
                ->where('reference_type', 'financial_transaction')
                ->sum('amount'); // Negative for payments, positive for collections

            // IMPORTANT: Cash refunds do NOT affect supplier balance
            // When we return items for CASH, supplier gives us money but our debt stays the same
            // Only CREDIT returns (captured in $returnsTotal) reduce what we owe them

            // Formula explanation for suppliers (positive balance = we owe them, negative = they owe us):
            // Start with: Opening Balance + What we bought (Purchases - can be negative if overpaid)
            // Subtract: What we returned (Returns) - reduces what we owe
            // ADD: What we paid (Payments is negative, so adding reduces the positive balance - we owe less)
            //      Example: 1500 owed + (-800 payment) = 700 owed
            // ADD: Financial transaction discounts (negative for payments, so adding reduces balance)
            // ADD: What they paid us back (Collections - when supplier owes us and pays us back)
            //      Collections are positive, adding them increases balance from negative towards zero

            return $openingBalance + $purchaseTotal - $returnsTotal + $payments + $financialDiscounts + $collections;

        } else { // shareholder
            // Shareholders use current_capital instead of current_balance
            // current_balance is not used for shareholders to avoid confusion
            return 0;
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
            // For suppliers: positive balance = we owe them, negative = they owe us
            return $balance >= 0
                ? 'له ' . number_format(abs($balance), 2)
                : 'عليه ' . number_format(abs($balance), 2);
        }
    }

    // Capital Management Methods

    /**
     * Recalculate partner capital from capital transactions
     */
    public function recalculateCapital(): float
    {
        $capital = $this->treasuryTransactions()
            ->whereIn('type', [
                'capital_deposit',
                'asset_contribution',
                'profit_allocation',
                'partner_drawing',
            ])
            ->sum('amount');

        $this->update(['current_capital' => $capital]);

        return $capital;
    }

    /**
     * Get current equity percentage
     */
    public function getCurrentEquityPercentage(): float
    {
        return floatval($this->equity_percentage ?? 0);
    }

    /**
     * Check if this partner is a manager
     */
    public function isManager(): bool
    {
        return $this->is_manager === true;
    }

    /**
     * Get capital ledger transactions
     */
    public function getCapitalLedger()
    {
        return $this->treasuryTransactions()
            ->whereIn('type', [
                'capital_deposit',
                'asset_contribution',
                'profit_allocation',
                'partner_drawing',
            ])
            ->orderBy('created_at', 'desc')
            ->get();
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

    public function scopeManagers($query)
    {
        return $query->where('is_manager', true);
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

    /**
     * Check if this partner has any associated financial records
     */
    public function hasAssociatedRecords(): bool
    {
        return $this->salesInvoices()->exists()
            || $this->purchaseInvoices()->exists()
            || $this->salesReturns()->exists()
            || $this->purchaseReturns()->exists()
            || $this->treasuryTransactions()->exists()
            || $this->invoicePayments()->exists();
    }

    // Deletion Protection
    protected static function booted(): void
    {
        static::deleting(function (Partner $partner) {
            if ($partner->hasAssociatedRecords()) {
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
