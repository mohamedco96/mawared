<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EquityPeriodResource\Pages;
use App\Models\EquityPeriod;
use App\Services\CapitalService;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EquityPeriodResource extends Resource
{
    protected static ?string $model = EquityPeriod::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $navigationLabel = 'فترات رأس المال (توزيع الأرباح)';

    protected static ?string $modelLabel = 'فترة';

    protected static ?string $pluralModelLabel = 'فترات رأس المال (توزيع الأرباح)';

    protected static ?string $navigationGroup = 'إدارة رأس المال (الشركاء)';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('معلومات الفترة')
                    ->schema([
                        Forms\Components\TextInput::make('period_number')
                            ->label('رقم الفترة')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\DatePicker::make('start_date')
                            ->label('تاريخ البداية')
                            ->required()
                            ->disabled(fn ($record) => $record && $record->status === 'closed'),

                        Forms\Components\DatePicker::make('end_date')
                            ->label('تاريخ النهاية')
                            ->disabled()
                            ->visible(fn ($record) => $record && $record->status === 'closed'),

                        Forms\Components\Select::make('status')
                            ->label('الحالة')
                            ->options(['open' => 'مفتوحة', 'closed' => 'مغلقة'])
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('الملخص المالي (الربح والخسارة)')
                    ->description(fn ($record) => $record && $record->status === 'open' ? 'يتم حساب القيم تلقائياً من البيانات الحالية' : null)
                    ->schema([
                        Forms\Components\Placeholder::make('total_revenue')
                            ->label('إجمالي الإيرادات (الفلوس اللي دخلت)')
                            ->content(function ($record) {
                                if (! $record) {
                                    return '—';
                                }
                                $value = $record->status === 'open'
                                    ? app(CapitalService::class)->getFinancialSummary($record)['total_revenue']
                                    : $record->total_revenue;

                                return number_format($value, 2) . ' ج.م';
                            }),

                        Forms\Components\Placeholder::make('total_expenses')
                            ->label('إجمالي المصروفات (الفلوس اللي خرجت)')
                            ->content(function ($record) {
                                if (! $record) {
                                    return '—';
                                }
                                $value = $record->status === 'open'
                                    ? app(CapitalService::class)->getFinancialSummary($record)['total_expenses']
                                    : $record->total_expenses;

                                return number_format($value, 2) . ' ج.م';
                            }),

                        Forms\Components\Placeholder::make('net_profit')
                            ->label('صافي الربح (المكسب الصافي)')
                            ->content(function ($record) {
                                if (! $record) {
                                    return '—';
                                }
                                $value = $record->status === 'open'
                                    ? app(CapitalService::class)->getFinancialSummary($record)['net_profit']
                                    : $record->net_profit;

                                return number_format($value, 2) . ' ج.م';
                            }),
                    ])
                    ->visible(fn ($record) => $record !== null)
                    ->columns(3),

                Forms\Components\Textarea::make('notes')
                    ->label('ملاحظات')
                    ->columnSpanFull()
                    ->disabled(fn ($record) => $record && $record->status === 'closed'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('period_number')
                    ->label('الفترة #')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('من')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('إلى')
                    ->dateTime('Y-m-d H:i:s')
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('net_profit')
                    ->label('صافي الربح (المكسب الصافي)')
                    ->state(function (EquityPeriod $record): float {
                        if ($record->status === 'open') {
                            return app(CapitalService::class)->getFinancialSummary($record)['net_profit'];
                        }

                        return $record->net_profit;
                    })
                    ->numeric(decimalPlaces: 2)
                    ->suffix(' ج.م')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : ($state < 0 ? 'danger' : 'gray')),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('الحالة')
                    ->formatStateUsing(fn ($state) => $state === 'open' ? 'مفتوحة' : 'مغلقة')
                    ->colors(['success' => 'open', 'gray' => 'closed']),

                Tables\Columns\TextColumn::make('closedBy.name')
                    ->label('أُغلقت بواسطة')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('closed_at')
                    ->label('تاريخ الإغلاق')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('—'),
            ])
            ->defaultSort('period_number', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('close_period')
                    ->label('إغلاق الفترة')
                    ->icon('heroicon-o-lock-closed')
                    ->color('warning')
                    ->visible(fn ($record) => $record->status === 'open')
                    ->requiresConfirmation()
                    ->modalHeading('إغلاق فترة رأس المال')
                    ->modalDescription('سيتم إغلاق الفترة بالتوقيت الحالي وحساب الربح وتوزيعه على الشركاء حسب نسبهم المقفلة. هل أنت متأكد؟')
                    ->form([
                        Forms\Components\Textarea::make('notes')
                            ->label('ملاحظات')
                            ->placeholder('سبب الإغلاق (اختياري)'),
                    ])
                    ->action(function (array $data, EquityPeriod $record) {
                        $capitalService = app(CapitalService::class);

                        try {
                            // Use current timestamp as the exact end time
                            $capitalService->closePeriodAndAllocate(
                                now(),
                                $data['notes'] ?? null
                            );

                            Notification::make()
                                ->success()
                                ->title('تم إغلاق الفترة بنجاح')
                                ->body('تم حساب وتوزيع الأرباح على الشركاء')
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('خطأ في إغلاق الفترة')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
            ])
            ->emptyStateHeading('لا توجد فترات')
            ->emptyStateDescription('ابدأ بإنشاء فترة رأس المال الأولى')
            ->emptyStateIcon('heroicon-o-chart-pie');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEquityPeriods::route('/'),
            'view' => Pages\ViewEquityPeriod::route('/{record}'),
        ];
    }
}
