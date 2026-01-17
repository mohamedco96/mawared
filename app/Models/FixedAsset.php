<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class FixedAsset extends Model
{
    use HasFactory, HasUlids, SoftDeletes, LogsActivity;

    protected $fillable = [
        'name',
        'description',
        'purchase_amount',
        'treasury_id',
        'purchase_date',
        'funding_method',
        'supplier_name',
        'supplier_id',
        'partner_id',
        'status',
        'created_by',
        'useful_life_years',
        'salvage_value',
        'accumulated_depreciation',
        'last_depreciation_date',
        'depreciation_method',
        'is_contributed_asset',
        'contributing_partner_id',
    ];

    protected function casts(): array
    {
        return [
            'purchase_amount' => 'decimal:4',
            'purchase_date' => 'date',
            'salvage_value' => 'decimal:4',
            'accumulated_depreciation' => 'decimal:4',
            'last_depreciation_date' => 'date',
            'is_contributed_asset' => 'boolean',
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'supplier_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function contributingPartner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'contributing_partner_id');
    }

    public function treasuryTransactions(): MorphMany
    {
        return $this->morphMany(TreasuryTransaction::class, 'reference');
    }

    // Business Logic
    public function isPosted(): bool
    {
        return $this->status === 'active';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    // Depreciation Methods
    public function calculateMonthlyDepreciation(): float
    {
        if (!$this->useful_life_years || $this->useful_life_years <= 0) {
            return 0;
        }

        // Straight-line: (Cost - Salvage) / (Useful Life in Months)
        $depreciableAmount = bcsub(
            (string)$this->purchase_amount,
            (string)$this->salvage_value,
            4
        );

        $totalMonths = $this->useful_life_years * 12;

        return floatval(bcdiv($depreciableAmount, (string)$totalMonths, 4));
    }

    public function getBookValue(): float
    {
        return floatval(bcsub(
            (string)$this->purchase_amount,
            (string)$this->accumulated_depreciation,
            4
        ));
    }

    public function needsDepreciation(): bool
    {
        if (!$this->useful_life_years) {
            return false;
        }

        if (!$this->last_depreciation_date) {
            return true;
        }

        return $this->last_depreciation_date < now()->startOfMonth();
    }

    // Activity Log
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'purchase_amount', 'treasury_id', 'purchase_date', 'funding_method', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
