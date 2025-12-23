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
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'expense_date' => 'date',
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

    public function treasuryTransactions(): MorphMany
    {
        return $this->morphMany(TreasuryTransaction::class, 'reference');
    }
}
