<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreference extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get a preference value by key for the current user
     */
    public static function get(string $key, $default = null)
    {
        if (!auth()->check()) {
            return $default;
        }

        $preference = static::where('user_id', auth()->id())
            ->where('key', $key)
            ->first();

        return $preference ? $preference->value : $default;
    }

    /**
     * Set a preference value by key for the current user
     */
    public static function set(string $key, $value): void
    {
        if (!auth()->check()) {
            return;
        }

        static::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'key' => $key,
            ],
            [
                'value' => $value,
            ]
        );
    }

    /**
     * Remove a preference by key for the current user
     */
    public static function forget(string $key): void
    {
        if (!auth()->check()) {
            return;
        }

        static::where('user_id', auth()->id())
            ->where('key', $key)
            ->delete();
    }
}
