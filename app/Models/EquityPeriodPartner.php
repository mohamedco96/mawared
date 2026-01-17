<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquityPeriodPartner extends Model
{
    use HasUlids;

    protected $table = 'equity_period_partners';

    protected $fillable = [
        'equity_period_id',
        'partner_id',
        'equity_percentage',
        'capital_at_start',
        'profit_allocated',
        'capital_injected',
        'drawings_taken',
    ];

    protected $casts = [
        'equity_percentage' => 'decimal:4',
        'capital_at_start' => 'decimal:4',
        'profit_allocated' => 'decimal:4',
        'capital_injected' => 'decimal:4',
        'drawings_taken' => 'decimal:4',
    ];

    /**
     * Get the equity period
     */
    public function equityPeriod(): BelongsTo
    {
        return $this->belongsTo(EquityPeriod::class);
    }

    /**
     * Get the partner
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
