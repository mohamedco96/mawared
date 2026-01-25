<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * TreasuryTransaction Model
 *
 * BLIND-04 FIX: Added SoftDeletes to allow transaction reversal/voiding
 * without losing audit trail. Financial transactions should never be
 * hard-deleted for compliance purposes.
 */
class TreasuryTransaction extends Model
{
    use HasFactory, HasUlids, LogsActivity, SoftDeletes;

    protected $fillable = [
        'treasury_id',
        'type',
        'amount',
        'description',
        'partner_id',
        'employee_id',
        'reference_type',
        'reference_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
        ];
    }

    // Relationships
    public function treasury(): BelongsTo
    {
        return $this->belongsTo(Treasury::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function reference(): MorphTo
    {
        // Use morphTo with proper handling for null reference_id
        // Some transaction types (capital_deposit, shareholder_investment, financial_transaction)
        // don't reference actual models, they just track the transaction type
        return $this->morphTo()->withDefault();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => match($eventName) {
                'created' => 'تم إنشاء معاملة خزينة',
                'updated' => 'تم تحديث معاملة خزينة',
                default => "معاملة خزينة {$eventName}",
            });
    }
}
