<?php

namespace App\Models;

use App\Settings\CompanySettings;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Quotation extends Model
{
    use HasFactory, HasUlids, SoftDeletes, LogsActivity;

    protected $fillable = [
        'quotation_number',
        'partner_id',
        'guest_name',
        'guest_phone',
        'pricing_type',
        'status',
        'public_token',
        'valid_until',
        'subtotal',
        'discount_type',
        'discount_value',
        'discount',
        'total',
        'notes',
        'internal_notes',
        'converted_invoice_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'valid_until' => 'date',
            'subtotal' => 'decimal:4',
            'discount_value' => 'decimal:4',
            'discount' => 'decimal:4',
            'total' => 'decimal:4',
        ];
    }

    // Relationships
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function convertedInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'converted_invoice_id');
    }

    // Scopes
    public function scopeValid($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('valid_until')
              ->orWhere('valid_until', '>=', now()->toDateString());
        });
    }

    public function scopeExpired($query)
    {
        return $query->whereNotNull('valid_until')
            ->where('valid_until', '<', now()->toDateString());
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeForPartner($query, string $partnerId)
    {
        return $query->where('partner_id', $partnerId);
    }

    // Accessors
    public function getCustomerNameAttribute(): string
    {
        return $this->partner?->name ?? $this->guest_name ?? 'غير محدد';
    }

    public function getCustomerPhoneAttribute(): ?string
    {
        return $this->partner?->phone ?? $this->guest_phone;
    }

    // Methods
    public function getPublicUrl(): string
    {
        return route('quotations.public', $this->public_token);
    }

    public function getWhatsAppUrl(): string
    {
        $companySettings = app(CompanySettings::class);
        $phone = $companySettings->company_phone;

        // Clean phone number (remove spaces, dashes, etc.)
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Ensure international format (add country code if needed)
        if (!str_starts_with($phone, '20')) {
            $phone = '20' . ltrim($phone, '0');
        }

        $message = "السلام عليكم، أرغب في الاستفسار عن عرض السعر رقم: {$this->quotation_number}\n\n";
        $message .= "رابط العرض: {$this->getPublicUrl()}";

        return "https://wa.me/{$phone}?text=" . urlencode($message);
    }

    public function canBeEdited(): bool
    {
        return in_array($this->status, ['draft', 'sent']);
    }

    public function canBeConverted(): bool
    {
        return $this->status !== 'converted' && !$this->isExpired();
    }

    public function isExpired(): bool
    {
        if (!$this->valid_until) {
            return false;
        }

        return $this->valid_until < now()->toDateString();
    }

    public function calculateTotals(): void
    {
        $this->subtotal = $this->items->sum('total');
        $this->total = $this->subtotal - $this->discount;
        $this->save();
    }

    public function hasAssociatedRecords(): bool
    {
        return $this->status === 'converted' || $this->converted_invoice_id !== null;
    }

    // Model Events
    protected static function booted(): void
    {
        static::creating(function (Quotation $quotation) {
            // Auto-generate quotation number
            if (empty($quotation->quotation_number)) {
                $quotation->quotation_number = static::generateQuotationNumber();
            }

            // Auto-generate public token
            if (empty($quotation->public_token)) {
                $quotation->public_token = Str::random(32);
            }

            // Set created_by to authenticated user
            if (empty($quotation->created_by)) {
                $quotation->created_by = auth()->id();
            }
        });

        static::updating(function (Quotation $quotation) {
            $original = $quotation->getOriginal('status');

            // Prevent editing converted quotations
            if ($original === 'converted' && $quotation->isDirty() && !$quotation->isDirty('status')) {
                throw new \Exception('لا يمكن تعديل عرض سعر محول إلى فاتورة');
            }
        });

        static::deleting(function (Quotation $quotation) {
            if ($quotation->hasAssociatedRecords()) {
                throw new \Exception('لا يمكن حذف عرض سعر محول إلى فاتورة');
            }
        });
    }

    protected static function generateQuotationNumber(): string
    {
        $companySettings = app(CompanySettings::class);
        $prefix = $companySettings->quotation_prefix ?? 'QT';
        $year = date('Y');

        // Get last quotation number for this year
        $lastQuotation = static::withTrashed()
            ->where('quotation_number', 'like', "{$prefix}-{$year}-%")
            ->orderByDesc('quotation_number')
            ->first();

        if ($lastQuotation) {
            $lastNumber = (int) substr($lastQuotation->quotation_number, strrpos($lastQuotation->quotation_number, '-') + 1);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return sprintf('%s-%s-%05d', $prefix, $year, $newNumber);
    }

    // Activity Logging
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['quotation_number', 'partner_id', 'guest_name', 'status', 'total'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('quotation')
            ->setDescriptionForEvent(fn(string $eventName) => match($eventName) {
                'created' => 'تم إنشاء عرض السعر',
                'updated' => 'تم تحديث عرض السعر',
                'deleted' => 'تم حذف عرض السعر',
                default => "عرض السعر {$eventName}",
            });
    }
}
