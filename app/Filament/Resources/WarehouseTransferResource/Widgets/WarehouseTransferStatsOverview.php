<?php

namespace App\Filament\Resources\WarehouseTransferResource\Widgets;

use App\Models\WarehouseTransfer;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class WarehouseTransferStatsOverview extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament.resources.warehouse-transfer-resource.widgets.warehouse-transfer-stats-overview';

    protected function getStats(): array
    {
        $totalTransfers = WarehouseTransfer::count();
        $pendingTransfers = WarehouseTransfer::whereDoesntHave('stockMovements')->count();
        $completedTransfers = WarehouseTransfer::whereHas('stockMovements')->count();

        return [
            Stat::make('إجمالي عمليات النقل', $totalTransfers)
                ->description('عدد عمليات النقل المسجلة')
                ->descriptionIcon('heroicon-m-truck')
                ->color('primary'),

            Stat::make('قيد الانتظار', $pendingTransfers)
                ->description('لم يتم تأكيدها بعد')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('مكتملة', $completedTransfers)
                ->description('تم تأكيد النقل')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
        ];
    }
}
