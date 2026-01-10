<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Spatie\Activitylog\Models\Activity;
use App\Filament\Resources\ActivityLogResource;

class LatestActivitiesWidget extends BaseWidget
{
    protected static ?int $sort = 10;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'آخر النشاطات';

    public static function canView(): bool
    {
        return auth()->user()?->can('widget_LatestActivitiesWidget') ?? false;
    }

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
                            'user' => 'مستخدم',
                            'product' => 'منتج',
                            'product_category' => 'فئة منتج',
                            'partner' => 'شريك',
                            'sales_invoice' => 'فاتورة مبيعات',
                            'purchase_invoice' => 'فاتورة مشتريات',
                            'sales_return' => 'مرتجع مبيعات',
                            'purchase_return' => 'مرتجع مشتريات',
                            'stock_movement' => 'حركة مخزون',
                            'treasury_transaction' => 'معاملة خزينة',
                            'initial_capital' => 'رأس المال الافتتاحي',
                            'fixed_asset' => 'أصل ثابت',
                            'quotation' => 'عرض سعر',
                            'installment' => 'قسط',
                        ];

                        return $modelMap[$state] ?? $state;
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
                    ->url(ActivityLogResource::getUrl('index'))
                    ->icon('heroicon-o-arrow-right')
                    ->color('primary'),
            ])
            ->paginated(false);
    }
}
