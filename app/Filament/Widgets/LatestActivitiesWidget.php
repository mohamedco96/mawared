<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Spatie\Activitylog\Models\Activity;

class LatestActivitiesWidget extends BaseWidget
{
    protected static ?int $sort = 10;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'آخر النشاطات';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Activity::query()
                    ->latest()
                    ->limit(3)
            )
            ->columns([
                Tables\Columns\ImageColumn::make('causer.name')
                    ->label('المستخدم')
                    ->getStateUsing(fn ($record) => $record->causer?->name)
                    ->default('—')
                    ->circular()
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->causer?->name ?? 'System') . '&color=7F9CF5&background=EBF4FF'),

                Tables\Columns\TextColumn::make('causer.name')
                    ->label('المستخدم')
                    ->default('النظام')
                    ->searchable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('الوصف')
                    ->searchable()
                    ->wrap()
                    ->limit(50),

                Tables\Columns\TextColumn::make('subject_type')
                    ->label('النموذج')
                    ->formatStateUsing(function (?string $state): string {
                        if (!$state) return '—';

                        $modelMap = [
                            'App\Models\User' => 'مستخدم',
                            'App\Models\Product' => 'منتج',
                            'App\Models\Partner' => 'شريك',
                            'App\Models\SalesInvoice' => 'فاتورة مبيعات',
                            'App\Models\PurchaseInvoice' => 'فاتورة مشتريات',
                            'App\Models\StockMovement' => 'حركة مخزون',
                            'App\Models\TreasuryTransaction' => 'معاملة خزينة',
                        ];

                        return $modelMap[$state] ?? class_basename($state);
                    })
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('event')
                    ->label('النوع')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match($state) {
                        'created' => 'إنشاء',
                        'updated' => 'تحديث',
                        'deleted' => 'حذف',
                        default => $state ?? '—',
                    })
                    ->color(fn (?string $state): string => match($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('الوقت')
                    ->since()
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('view_all')
                    ->label('عرض السجل الكامل')
                    ->url(route('filament.admin.resources.activity-logs.index'))
                    ->icon('heroicon-o-arrow-right')
                    ->color('primary'),
            ])
            ->paginated(false);
    }
}
