<?php

namespace App\Filament\Resources\ProductCategoryResource\Widgets;

use App\Models\ProductCategory;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProductCategoryStatsOverview extends BaseWidget
{
    protected static string $view = 'filament.resources.product-category-resource.widgets.product-category-stats-overview';

    protected function getStats(): array
    {
        $totalCategories = ProductCategory::count();
        $mainCategories = ProductCategory::whereNull('parent_id')->count();
        $subCategories = ProductCategory::whereNotNull('parent_id')->count();
        $activeCategories = ProductCategory::where('is_active', true)->count();

        return [
            Stat::make('إجمالي التصنيفات', $totalCategories)
                ->description('عدد التصنيفات المسجلة')
                ->descriptionIcon('heroicon-m-folder')
                ->color('primary'),
            Stat::make('تصنيفات رئيسية', $mainCategories)
                ->description('عدد التصنيفات الرئيسية')
                ->descriptionIcon('heroicon-m-folder-open')
                ->color('info'),
            Stat::make('تصنيفات فرعية', $subCategories)
                ->description('عدد التصنيفات الفرعية')
                ->descriptionIcon('heroicon-m-folder-minus')
                ->color('warning'),
            Stat::make('تصنيفات نشطة', $activeCategories)
                ->description('عدد التصنيفات النشطة')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
        ];
    }
}
