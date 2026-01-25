<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Expense extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'title',
        'description',
        'amount',
        'treasury_id',
        'expense_date',
        'created_by',
        'expense_category_id',
        'beneficiary_name',
        'attachment',
        'is_non_cash',
        'fixed_asset_id',
        'depreciation_period',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'expense_date' => 'datetime',
            'is_non_cash' => 'boolean',
            'depreciation_period' => 'date',
        ];
    }

    // Relationships
    public function treasury(): BelongsTo
    {
        return $this->belongsTo(Treasury::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class);
    }

    public function treasuryTransactions(): MorphMany
    {
        return $this->morphMany(TreasuryTransaction::class, 'reference');
    }

    // Scopes
    public function scopeNonCash($query)
    {
        return $query->where('is_non_cash', true);
    }

    public function scopeCashExpenses($query)
    {
        return $query->where('is_non_cash', false);
    }

    public function scopeDepreciation($query)
    {
        return $query->whereNotNull('fixed_asset_id');
    }
}
