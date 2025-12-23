<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TreasuryTransaction extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'treasury_id',
        'type',
        'amount',
        'description',
        'partner_id',
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
        return $this->morphTo();
    }
}
