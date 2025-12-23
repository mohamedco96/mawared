<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'name',
        'code',
        'address',
    ];

    // Relationships
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function salesInvoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class);
    }

    public function purchaseInvoices(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class);
    }

    public function stockAdjustments(): HasMany
    {
        return $this->hasMany(StockAdjustment::class);
    }

    public function fromTransfers(): HasMany
    {
        return $this->hasMany(WarehouseTransfer::class, 'from_warehouse_id');
    }

    public function toTransfers(): HasMany
    {
        return $this->hasMany(WarehouseTransfer::class, 'to_warehouse_id');
    }
}
