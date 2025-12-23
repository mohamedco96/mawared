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
}
