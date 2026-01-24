<?php

namespace App\Enums;

enum ExpenseCategoryType: string
{
    case OPERATIONAL = 'operational';
    case ADMIN = 'admin';
    case MARKETING = 'marketing';

    /**
     * Get Arabic label for the expense category type
     */
    public function getLabel(): string
    {
        return match($this) {
            self::OPERATIONAL => 'تشغيلية',
            self::ADMIN => 'إدارية',
            self::MARKETING => 'تسويقية',
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
