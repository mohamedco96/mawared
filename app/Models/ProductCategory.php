<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ProductCategory extends Model
{
    use HasFactory, HasUlids, SoftDeletes, LogsActivity;

    protected $fillable = [
        'parent_id',
        'name',
        'name_en',
        'slug',
        'description',
        'image',
        'is_active',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    // Relationships
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ProductCategory::class, 'parent_id')->orderBy('display_order');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    // Model Events
    protected static function booted(): void
    {
        static::creating(function (ProductCategory $category) {
            // Auto-generate slug if empty
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);

                // Ensure uniqueness
                $count = 1;
                while (static::where('slug', $category->slug)->exists()) {
                    $category->slug = Str::slug($category->name) . '-' . $count;
                    $count++;
                }
            }
        });

        static::deleting(function (ProductCategory $category) {
            // Check for related products
            $hasProducts = $category->products()->exists();
            if ($hasProducts) {
                throw new \Exception('لا يمكن حذف التصنيف لوجود منتجات مرتبطة به');
            }

            // Check for child categories
            $hasChildren = $category->children()->exists();
            if ($hasChildren) {
                throw new \Exception('لا يمكن حذف التصنيف لوجود تصنيفات فرعية مرتبطة به');
            }
        });
    }

    // Activity Logging
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => match($eventName) {
                'created' => 'تم إنشاء تصنيف منتج',
                'updated' => 'تم تحديث تصنيف منتج',
                'deleted' => 'تم حذف تصنيف منتج',
                default => "تصنيف منتج {$eventName}",
            });
    }
}
