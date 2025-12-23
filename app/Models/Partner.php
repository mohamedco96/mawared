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
            'current_balance' => 'decimal:4',
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

    // Scopes
    public function scopeCustomers($query)
    {
        return $query->where('type', 'customer');
    }

    public function scopeSuppliers($query)
    {
        return $query->where('type', 'supplier');
    }
}
