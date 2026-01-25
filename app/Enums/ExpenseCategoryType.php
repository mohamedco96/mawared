<?php

namespace App\Enums;

enum ExpenseCategoryType: string
{
    case OPERATIONAL = 'operational';
    case ADMIN = 'admin';
    case MARKETING = 'marketing';
    case DEPRECIATION = 'depreciation';

    /**
     * Get Arabic label for the expense category type
     */
    public function getLabel(): string
    {
        return match($this) {
            self::OPERATIONAL => 'تشغيلية',
            self::ADMIN => 'إدارية',
            self::MARKETING => 'تسويقية',
            self::DEPRECIATION => 'استهلاك أصول',
        };
    }

    /**
     * Get badge color for display
     */
    public function getColor(): string
    {
        return match($this) {
            self::OPERATIONAL => 'primary',
            self::ADMIN => 'info',
            self::MARKETING => 'success',
            self::DEPRECIATION => 'warning',
        };
    }

    /**
     * Check if this expense type is non-cash (doesn't affect treasury)
     */
    public function isNonCash(): bool
    {
        return match($this) {
            self::DEPRECIATION => true,
            default => false,
        };
    }

    /**
     * Get all types as options array for Filament
     */
    public static function getSelectOptions(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $type) => [$type->value => $type->getLabel()])
            ->toArray();
    }
}
