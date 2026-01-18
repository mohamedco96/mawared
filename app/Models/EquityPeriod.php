<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

class EquityPeriod extends Model
{
    use HasUlids;

    protected $fillable = [
        'period_number',
        'start_date',
        'end_date',
        'status',
        'net_profit',
        'total_revenue',
        'total_expenses',
        'closed_at',
        'closed_by',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'closed_at' => 'datetime',
        'net_profit' => 'decimal:4',
        'total_revenue' => 'decimal:4',
        'total_expenses' => 'decimal:4',
    ];

    /**
     * Get the partners associated with this period
     */
    public function partners(): BelongsToMany
    {
        return $this->belongsToMany(Partner::class, 'equity_period_partners')
            ->withPivot([
                'equity_percentage',
                'capital_at_start',
                'profit_allocated',
                'capital_injected',
                'drawings_taken',
            ])
            ->withTimestamps();
    }

    /**
     * Get the user who closed this period
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Scope to get only open periods
     */
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    /**
     * Scope to get only closed periods
     */
    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    /**
     * Scope to get period containing a specific date
     */
    public function scopeForDate($query, $date)
    {
        return $query->where('start_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $date);
            });
    }

    /**
     * Check if this period is open
     */
    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Check if this period is closed
     */
    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * Close this period
     */
    public function close(User $user, ?string $notes = null): void
    {
        $this->update([
            'status' => 'closed',
            'closed_by' => $user->id,
            'closed_at' => now(),
            'notes' => $notes,
        ]);
    }

    /**
     * Get period number display
     */
    public function getPeriodDisplayAttribute(): string
    {
        return "Period #{$this->period_number}";
    }
}
