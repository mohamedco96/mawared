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
        'default_profit_margin',
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

    /**
     * Check if this category has any associated records that prevent deletion
     */
    public function hasAssociatedRecords(): bool
    {
        return $this->products()->exists() || $this->children()->exists();
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

        static::saving(function (ProductCategory $category) {
            // Prevent category from being its own parent (only check if category exists and parent_id is set)
            if ($category->exists && $category->parent_id && $category->parent_id === $category->id) {
                throw new \Exception('لا يمكن للتصنيف أن يكون أب لنفسه');
            }

            // Prevent circular references (category cannot be a descendant of itself)
            if ($category->parent_id && $category->exists) {
                $parentId = $category->parent_id;
                $visited = [$category->id];

                while ($parentId) {
                    if (in_array($parentId, $visited)) {
                        throw new \Exception('لا يمكن إنشاء تسلسل دائري في التصنيفات');
                    }

                    $visited[] = $parentId;
                    $parent = static::find($parentId);
                    $parentId = $parent?->parent_id;
                }
            }
        });

        static::deleting(function (ProductCategory $category) {
            if ($category->hasAssociatedRecords()) {
                throw new \Exception('لا يمكن حذف التصنيف لوجود منتجات أو تصنيفات فرعية مرتبطة به');
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
