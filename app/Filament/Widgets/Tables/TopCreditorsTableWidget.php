<?php

namespace App\Filament\Widgets\Tables;

use App\Filament\Resources\PartnerResource;
use App\Models\Partner;
use Closure;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class TopCreditorsTableWidget extends BaseWidget
{
    protected static ?string $heading = 'أكبر 5 موردين دائنون';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

    protected static ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->can('widget_TopCreditorsTableWidget') ?? false;
    }

    protected function getTableQuery(): Builder
    {
        return Partner::query()
            ->where('type', 'supplier')
            ->where('current_balance', '<', 0)
            ->orderBy('current_balance', 'asc') // Most negative first
            ->limit(5);
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('name')
                ->label('اسم المورد')
                ->searchable()
                ->sortable()
                ->weight('medium'),

            Tables\Columns\TextColumn::make('phone')
                ->label('الهاتف')
                ->searchable()
                ->default('—')
                ->icon('heroicon-m-phone'),

            Tables\Columns\TextColumn::make('current_balance')
                ->label('الرصيد المستحق')
                ->sortable()
                ->formatStateUsing(fn ($state) => number_format(abs($state), 2) . ' ج.م')
                ->color('warning')
                ->weight('bold')
                ->alignEnd(),
        ];
    }

    protected function getTableRecordUrlUsing(): ?Closure
    {
        return fn (Partner $record): string => PartnerResource::getUrl('edit', ['record' => $record]);
    }
}
