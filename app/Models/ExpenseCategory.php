<?php

namespace App\Models;

use App\Enums\ExpenseCategoryType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseCategory extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => ExpenseCategoryType::class,
            'is_active' => 'boolean',
        ];
    }

    // Relationships
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if this category has any associated expenses
     */
    public function hasExpenses(): bool
    {
        return $this->expenses()->exists();
    }

    // Model Events
    protected static function booted(): void
    {
        static::deleting(function (ExpenseCategory $category) {
            if ($category->hasExpenses()) {
                throw new \Exception('لا يمكن حذف التصنيف لوجود مصروفات مرتبطة به');
            }
        });
    }
}
