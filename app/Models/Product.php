<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Product extends Model
{
    use HasFactory, HasUlids, LogsActivity, SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'description',
        'image',
        'images',
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
        'is_visible_in_retail_catalog',
        'is_visible_in_wholesale_catalog',
    ];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'min_stock' => 'integer',
            'avg_cost' => 'decimal:4',
            'factor' => 'integer',
            'retail_price' => 'decimal:4',
            'wholesale_price' => 'decimal:4',
            'large_retail_price' => 'decimal:4',
            'large_wholesale_price' => 'decimal:4',
            'is_visible_in_retail_catalog' => 'boolean',
            'is_visible_in_wholesale_catalog' => 'boolean',
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }

    public function salesInvoiceItems(): HasMany
    {
        return $this->hasMany(SalesInvoiceItem::class);
    }

    // Scopes
    public function scopeRetailCatalog($query)
    {
        return $query->where('is_visible_in_retail_catalog', true);
    }

    public function scopeWholesaleCatalog($query)
    {
        return $query->where('is_visible_in_wholesale_catalog', true);
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
        // Validate non-negative values before saving
        static::saving(function (Product $product) {
            if ($product->min_stock < 0) {
                throw new \InvalidArgumentException('الحد الأدنى للمخزون لا يمكن أن يكون قيمة سالبة');
            }
            if ($product->retail_price < 0) {
                throw new \InvalidArgumentException('سعر التجزئة لا يمكن أن يكون قيمة سالبة');
            }
            if ($product->wholesale_price < 0) {
                throw new \InvalidArgumentException('سعر الجملة لا يمكن أن يكون قيمة سالبة');
            }
            if ($product->large_retail_price !== null && $product->large_retail_price < 0) {
                throw new \InvalidArgumentException('سعر التجزئة للوحدة الكبيرة لا يمكن أن يكون قيمة سالبة');
            }
            if ($product->large_wholesale_price !== null && $product->large_wholesale_price < 0) {
                throw new \InvalidArgumentException('سعر الجملة للوحدة الكبيرة لا يمكن أن يكون قيمة سالبة');
            }
        });

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

        // Deletion validation is handled in the Filament Resource to show proper toast notifications
    }

    /**
     * Generate a unique code for barcode, sku, or large_barcode
     */
    private static function generateUniqueCode(string $field): string
    {
        // Define prefixes for each field type
        $prefixes = [
            'barcode' => 'BC',
            'large_barcode' => 'LB',
            'sku' => 'SKU',
        ];

        $prefix = $prefixes[$field] ?? 'PRD';

        do {
            // Generate timestamp-based code with random suffix
            $timestamp = substr(time(), -6); // Last 6 digits of timestamp
            $random = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 4));
            $code = $prefix . $timestamp . $random;
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
            'السعر' => number_format($this->retail_price, 2).' ج.م',
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'barcode', 'sku', 'large_barcode'];
    }

    // Accessors for Showroom
    public function getStockAttribute(): int
    {
        return (int) $this->stockMovements()->sum('quantity');
    }

    public function getDisplayImageAttribute(): ?string
    {
        if ($this->image) {
            return filter_var($this->image, FILTER_VALIDATE_URL)
                ? $this->image
                : \Storage::url($this->image);
        }

        return null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => match ($eventName) {
                'created' => 'تم إنشاء منتج',
                'updated' => 'تم تحديث منتج',
                'deleted' => 'تم حذف منتج',
                default => "المنتج {$eventName}",
            });
    }
}
