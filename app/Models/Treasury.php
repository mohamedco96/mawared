<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Treasury extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'name',
        'type',
        'description',
    ];

    // Relationships
    public function treasuryTransactions(): HasMany
    {
        return $this->hasMany(TreasuryTransaction::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function revenues(): HasMany
    {
        return $this->hasMany(Revenue::class);
    }

    public function fixedAssets(): HasMany
    {
        return $this->hasMany(FixedAsset::class);
    }

    // Accessors
    public function getBalanceAttribute(): float
    {
        return (float) $this->treasuryTransactions()->sum('amount');
    }

    /**
     * Check if this treasury has any associated records that prevent deletion
     */
    public function hasAssociatedRecords(): bool
    {
        return $this->treasuryTransactions()->exists() ||
            $this->expenses()->exists() ||
            $this->revenues()->exists() ||
            $this->fixedAssets()->exists();
    }

    // Model Events
    protected static function booted(): void
    {
        static::deleting(function (Treasury $treasury) {
            if ($treasury->hasAssociatedRecords()) {
                throw new \Exception('لا يمكن حذف الخزينة لوجود معاملات مالية أو مصروفات أو إيرادات أو أصول ثابتة مرتبطة بها.');
            }
        });
    }
}
