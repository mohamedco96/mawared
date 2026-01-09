<?php

namespace App\Traits;

use App\Models\UserPreference;

trait HasPreferences
{
    /**
     * Get all preferences for this user
     */
    public function preferences()
    {
        return $this->hasMany(UserPreference::class);
    }

    /**
     * Get a preference value by key
     */
    public function getPreference(string $key, $default = null)
    {
        $preference = $this->preferences()
            ->where('key', $key)
            ->first();

        return $preference ? $preference->value : $default;
    }

    /**
     * Set a preference value by key
     */
    public function setPreference(string $key, $value): void
    {
        $this->preferences()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    /**
     * Remove a preference by key
     */
    public function forgetPreference(string $key): void
    {
        $this->preferences()
            ->where('key', $key)
            ->delete();
    }

    /**
     * Check if a preference exists
     */
    public function hasPreference(string $key): bool
    {
        return $this->preferences()
            ->where('key', $key)
            ->exists();
    }
}
