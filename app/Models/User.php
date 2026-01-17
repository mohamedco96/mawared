<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\HasPreferences;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasUlids, LogsActivity, HasRoles, HasPreferences;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'national_id',
        'salary_type',
        'salary_amount',
        'advance_balance',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'salary_amount' => 'decimal:4',
            'advance_balance' => 'decimal:4',
        ];
    }

    public function treasuryTransactions(): HasMany
    {
        return $this->hasMany(TreasuryTransaction::class, 'employee_id');
    }

    public function salesInvoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class, 'sales_person_id');
    }

    /**
     * Get total commissions earned from all posted sales invoices
     */
    public function getTotalCommissionsEarned(): float
    {
        return $this->salesInvoices()
            ->where('status', 'posted')
            ->sum('commission_amount');
    }

    /**
     * Get unpaid commissions from posted sales invoices
     */
    public function getUnpaidCommissions(): float
    {
        return $this->salesInvoices()
            ->where('status', 'posted')
            ->where('commission_paid', false)
            ->sum('commission_amount');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => match($eventName) {
                'created' => 'تم إنشاء مستخدم',
                'updated' => 'تم تحديث مستخدم',
                'deleted' => 'تم حذف مستخدم',
                default => "المستخدم {$eventName}",
            });
    }

    /**
     * Determine if the user can access the Filament admin panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // Allow access to authorized Osool ERP team members
        $authorizedEmails = [
            'mohamed@osoolerp.com',
            'ashraf@osoolerp.com',
            'mahmoud@osoolerp.com',
            'rehab@osoolerp.com',
        ];

        // First check if email is authorized
        if (!in_array($this->email, $authorizedEmails, true)) {
            return false;
        }

        // If user has super_admin role, always allow
        if ($this->hasRole('super_admin')) {
            return true;
        }

        // For other authorized users, check if they have the panel_user role
        // or any role assigned (Shield requirement)
        return $this->roles()->exists();
    }
}
