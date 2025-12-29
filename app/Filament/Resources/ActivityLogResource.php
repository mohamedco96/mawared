<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'سجل النشاطات';
    protected static ?string $modelLabel = 'نشاط';
    protected static ?string $pluralModelLabel = 'سجل النشاطات';
    protected static ?string $navigationGroup = 'الإدارة';
    protected static ?int $navigationSort = 100;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('causer.name')
                    ->label('المستخدم')
                    ->getStateUsing(fn ($record) => $record->causer?->name ?? 'النظام')
                    ->circular()
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->causer?->name ?? 'System') . '&color=7F9CF5&background=EBF4FF'),

                Tables\Columns\TextColumn::make('causer.name')
                    ->label('اسم المستخدم')
                    ->default('النظام (System)')
                    ->description(fn ($record) => $record->causer ? $record->causer->email : null)
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('الإجراء')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'created' => 'تم إنشاء',
                        'updated' => 'تم تحديث',
                        'deleted' => 'تم حذف',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('subject_type')
                    ->label('نوع السجل')
                    ->formatStateUsing(function ($state) {
                        if (!$state) return '—';
                        return match ($state) {
                            'App\Models\User' => 'مستخدم',
                            'App\Models\Product' => 'منتج',
                            'App\Models\Partner' => 'شريك',
                            'App\Models\SalesInvoice' => 'فاتورة مبيعات',
                            'App\Models\PurchaseInvoice' => 'فاتورة مشتريات',
                            'App\Models\StockMovement' => 'حركة مخزون',
                            'App\Models\TreasuryTransaction' => 'معاملة مالية',
                            'App\Models\FixedAsset' => 'أصل ثابت',
                            default => class_basename($state),
                        };
                    })
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('subject_id')
                    ->label('السجل المتأثر')
                    ->formatStateUsing(function ($state, $record) {
                        if (!$record->subject) {
                            return '#' . $record->subject_id . ' (محذوف)';
                        }

                        $subject = $record->subject;

                        if (isset($subject->name)) {
                            return $subject->name;
                        } elseif (isset($subject->invoice_number)) {
                            return 'فاتورة #' . $subject->invoice_number;
                        }

                        return '#' . $record->subject_id;
                    })
                    ->url(fn ($record): ?string => static::getSubjectUrl($record), shouldOpenInNewTab: true)
                    ->icon(fn ($record) => $record->subject ? 'heroicon-o-arrow-top-right-on-square' : null)
                    ->color('primary')
                    ->weight('medium')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('event')
                    ->label('نوع العملية')
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
                    ->label('التاريخ والوقت')
                    ->dateTime('d M Y, h:i A')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('causer_id')
                    ->label('المستخدم')
                    ->options(function () {
                        return \App\Models\User::pluck('name', 'id');
                    })
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('event')
                    ->label('نوع العملية')
                    ->options([
                        'created' => 'إنشاء',
                        'updated' => 'تحديث',
                        'deleted' => 'حذف',
                    ])
                    ->native(false),

                Tables\Filters\SelectFilter::make('subject_type')
                    ->label('نوع السجل')
                    ->options([
                        'App\Models\User' => 'مستخدم',
                        'App\Models\Product' => 'منتج',
                        'App\Models\Partner' => 'شريك',
                        'App\Models\SalesInvoice' => 'فاتورة مبيعات',
                        'App\Models\PurchaseInvoice' => 'فاتورة مشتريات',
                        'App\Models\StockMovement' => 'حركة مخزون',
                        'App\Models\TreasuryTransaction' => 'معاملة مالية',
                        'App\Models\FixedAsset' => 'أصل ثابت',
                    ])
                    ->native(false),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('created_from')
                            ->label('من تاريخ'),
                        \Filament\Forms\Components\DatePicker::make('created_until')
                            ->label('إلى تاريخ'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['created_from'], fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'], fn ($query, $date) => $query->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('عرض التفاصيل'),
            ])
            ->bulkActions([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('معلومات النشاط')
                    ->schema([
                        Infolists\Components\TextEntry::make('causer.name')
                            ->label('المستخدم')
                            ->default('النظام (System)')
                            ->icon(fn ($state) => $state ? 'heroicon-o-user' : 'heroicon-o-cpu-chip')
                            ->color(fn ($state) => $state ? 'primary' : 'gray'),

                        Infolists\Components\TextEntry::make('description')
                            ->label('الوصف'),

                        Infolists\Components\TextEntry::make('event')
                            ->label('نوع العملية')
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

                        Infolists\Components\TextEntry::make('subject_type')
                            ->label('نوع السجل')
                            ->formatStateUsing(function ($state) {
                                if (!$state) return '—';
                                return match ($state) {
                                    'App\Models\User' => 'مستخدم',
                                    'App\Models\Product' => 'منتج',
                                    'App\Models\Partner' => 'شريك',
                                    'App\Models\SalesInvoice' => 'فاتورة مبيعات',
                                    'App\Models\PurchaseInvoice' => 'فاتورة مشتريات',
                                    'App\Models\StockMovement' => 'حركة مخزون',
                                    'App\Models\TreasuryTransaction' => 'معاملة مالية',
                                    'App\Models\FixedAsset' => 'أصل ثابت',
                                    default => class_basename($state),
                                };
                            })
                            ->badge()
                            ->color('info'),

                        Infolists\Components\TextEntry::make('subject_link')
                            ->label('السجل المتأثر')
                            ->state(function ($record) {
                                if (!$record->subject) {
                                    return '#' . $record->subject_id . ' (محذوف)';
                                }

                                $subject = $record->subject;
                                $displayName = '';

                                if (isset($subject->name)) {
                                    $displayName = $subject->name;
                                } elseif (isset($subject->invoice_number)) {
                                    $displayName = 'فاتورة #' . $subject->invoice_number;
                                } else {
                                    $displayName = '#' . $record->subject_id;
                                }

                                $url = static::getSubjectUrl($record);

                                if ($url) {
                                    return '<a href="' . htmlspecialchars($url) . '" target="_blank" class="text-primary-600 hover:text-primary-700 hover:underline font-medium inline-flex items-center gap-1">
                                        ' . htmlspecialchars($displayName) . '
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                        </svg>
                                    </a>';
                                }

                                return htmlspecialchars($displayName);
                            })
                            ->html(),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('التاريخ والوقت')
                            ->dateTime('d M Y, h:i A')
                            ->icon('heroicon-o-clock'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('التغييرات')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\KeyValueEntry::make('properties.attributes')
                                    ->label('القيم الجديدة / الحالية')
                                    ->columnSpan(1)
                                    ->visible(fn ($record) =>
                                        $record->properties &&
                                        isset($record->properties['attributes']) &&
                                        !empty($record->properties['attributes'])
                                    ),

                                Infolists\Components\KeyValueEntry::make('properties.old')
                                    ->label('القيم القديمة')
                                    ->columnSpan(1)
                                    ->visible(fn ($record) =>
                                        $record->properties &&
                                        isset($record->properties['old']) &&
                                        !empty($record->properties['old']) &&
                                        in_array($record->event, ['updated', 'deleted'])
                                    ),
                            ]),
                    ])
                    ->visible(fn ($record) =>
                        $record->properties &&
                        (
                            (isset($record->properties['attributes']) && !empty($record->properties['attributes'])) ||
                            (isset($record->properties['old']) && !empty($record->properties['old']))
                        )
                    )
                    ->collapsed(false),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
            'view' => Pages\ViewActivityLog::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    protected static function getSubjectUrl($record): ?string
    {
        if (!$record->subject || !$record->subject_type) {
            return null;
        }

        $resourceMap = [
            'App\Models\Product' => \App\Filament\Resources\ProductResource::class,
            'App\Models\SalesInvoice' => \App\Filament\Resources\SalesInvoiceResource::class,
            'App\Models\PurchaseInvoice' => \App\Filament\Resources\PurchaseInvoiceResource::class,
            'App\Models\Partner' => \App\Filament\Resources\PartnerResource::class,
            'App\Models\User' => \App\Filament\Resources\UserResource::class,
            'App\Models\FixedAsset' => \App\Filament\Resources\FixedAssetResource::class,
        ];

        $resourceClass = $resourceMap[$record->subject_type] ?? null;

        if (!$resourceClass || !class_exists($resourceClass)) {
            return null;
        }

        // Try 'view' first for invoices, then fallback to 'edit'
        try {
            if (in_array($record->subject_type, ['App\Models\SalesInvoice', 'App\Models\PurchaseInvoice'])) {
                return $resourceClass::getUrl('view', ['record' => $record->subject_id]);
            }
            return $resourceClass::getUrl('edit', ['record' => $record->subject_id]);
        } catch (\Exception $e) {
            // Fallback to edit if view doesn't exist
            try {
                return $resourceClass::getUrl('edit', ['record' => $record->subject_id]);
            } catch (\Exception $e) {
                return null;
            }
        }
    }
}
