<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'name',
        'barcode',
        'large_barcode',
        'sku',
        'min_stock',
        'avg_cost',
        'small_unit_id',
        'large_unit_id',
        'factor',
        'retail_price',
        'wholesale_price',
        'large_retail_price',
        'large_wholesale_price',
    ];

    protected function casts(): array
    {
        return [
            'min_stock' => 'integer',
            'avg_cost' => 'decimal:2',
            'factor' => 'integer',
            'retail_price' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'large_retail_price' => 'decimal:2',
            'large_wholesale_price' => 'decimal:2',
        ];
    }

    // Relationships
    public function smallUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'small_unit_id');
    }

    public function largeUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'large_unit_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    // Helper Methods
    public function convertToBaseUnit(int $quantity, string $unitType): int
    {
        if ($unitType === 'large' && $this->large_unit_id) {
            return $quantity * $this->factor;
        }
        return $quantity;
    }

    // Model Events
    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            // Auto-generate barcode if null
            if (empty($product->barcode)) {
                $product->barcode = self::generateUniqueCode('barcode');
            }

            // Auto-generate SKU if null
            if (empty($product->sku)) {
                $product->sku = self::generateUniqueCode('sku');
            }

            // Auto-generate large_barcode if large_unit_id is set but large_barcode is null
            if ($product->large_unit_id && empty($product->large_barcode)) {
                $product->large_barcode = self::generateUniqueCode('large_barcode');
            }
        });

        static::updating(function (Product $product) {
            // Auto-generate large_barcode if large_unit_id is newly set but large_barcode is still null
            if ($product->large_unit_id && empty($product->large_barcode)) {
                $product->large_barcode = self::generateUniqueCode('large_barcode');
            }
        });

        static::deleting(function (Product $product) {
            // Check for related records to prevent deletion
            $hasStockMovements = \App\Models\StockMovement::where('product_id', $product->id)->exists();
            $hasSalesInvoiceItems = \App\Models\SalesInvoiceItem::where('product_id', $product->id)->exists();
            $hasPurchaseInvoiceItems = \App\Models\PurchaseInvoiceItem::where('product_id', $product->id)->exists();

            if ($hasStockMovements || $hasSalesInvoiceItems || $hasPurchaseInvoiceItems) {
                throw new \Exception('لا يمكن حذف المنتج لوجود فواتير أو حركات مخزون مرتبطة به');
            }
        });
    }

    /**
     * Generate a unique code for barcode, sku, or large_barcode
     */
    private static function generateUniqueCode(string $field): string
    {
        do {
            // Generate a random 10-character alphanumeric code
            $code = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 10));
        } while (self::where($field, $code)->exists());

        return $code;
    }

    // Global Search Implementation
    public function getGlobalSearchResultTitle(): string
    {
        return $this->name;
    }

    public function getGlobalSearchResultDetails(): array
    {
        return [
            'باركود' => $this->barcode,
            'رمز المنتج' => $this->sku,
            'السعر' => number_format($this->retail_price, 2) . ' ج.م',
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'barcode', 'sku', 'large_barcode'];
    }
}
