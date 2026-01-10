<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PartnerResource\Pages;
use App\Models\Partner;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PartnerResource extends Resource
{
    protected static ?string $model = Partner::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'العملاء والموردين';

    protected static ?string $modelLabel = 'شريك';

    protected static ?string $pluralModelLabel = 'العملاء والموردين';

    protected static ?string $navigationGroup = 'الإدارة المالية';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('معلومات أساسية')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('الاسم')
                            ->required()
                            ->maxLength(255)
                            ->autofocus()
                            ->columnSpanFull(),
                        Forms\Components\Select::make('type')
                            ->label('النوع')
                            ->options([
                                'customer' => 'عميل',
                                'supplier' => 'مورد',
                                'shareholder' => 'شريك (مساهم)',
                            ])
                            ->required()
                            ->native(false),
                        Forms\Components\TextInput::make('phone')
                            ->label('الهاتف')
                            ->tel()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('gov_id')
                            ->label('الهوية الوطنية')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('region')
                            ->label('المنطقة')
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('الحالة')
                    ->schema([
                        Forms\Components\Toggle::make('is_banned')
                            ->label('محظور')
                            ->default(false),
                        Forms\Components\TextInput::make('current_balance')
                            ->label('الرصيد الحالي')
                            ->numeric()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                            ->disabled()
                            ->dehydrated()
                            ->default(0),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('النوع')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'customer' => 'عميل',
                        'supplier' => 'مورد',
                        'shareholder' => 'شريك (مساهم)',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match($state) {
                        'customer' => 'success',
                        'supplier' => 'info',
                        'shareholder' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('phone')
                    ->label('الهاتف')
                    ->searchable(),
                Tables\Columns\TextColumn::make('gov_id')
                    ->label('الهوية الوطنية')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('region')
                    ->label('المنطقة')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('current_balance')
                    ->label('الرصيد')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->color(fn ($state) => $state < 0 ? 'danger' : ($state > 0 ? 'success' : 'gray')),
                Tables\Columns\IconColumn::make('is_banned')
                    ->label('محظور')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('النوع')
                    ->options([
                        'customer' => 'عميل',
                        'supplier' => 'مورد',
                        'shareholder' => 'شريك (مساهم)',
                    ])
                    ->native(false),
                Tables\Filters\TernaryFilter::make('is_banned')
                    ->label('محظور'),
                Tables\Filters\SelectFilter::make('region')
                    ->label('المنطقة')
                    ->options(function () {
                        return Partner::query()
                            ->whereNotNull('region')
                            ->distinct()
                            ->pluck('region', 'region')
                            ->toArray();
                    })
                    ->searchable(),
                Tables\Filters\Filter::make('current_balance')
                    ->label('الرصيد')
                    ->form([
                        Forms\Components\TextInput::make('from')
                            ->label('من')
                            ->numeric()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                            ->step(0.01),
                        Forms\Components\TextInput::make('until')
                            ->label('إلى')
                            ->numeric()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                            ->step(0.01),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $balance) => $q->where('current_balance', '>=', $balance))
                            ->when($data['until'], fn ($q, $balance) => $q->where('current_balance', '<=', $balance));
                    }),
                Tables\Filters\Filter::make('balance_status')
                    ->label('حالة الرصيد')
                    ->form([
                        Forms\Components\Select::make('status')
                            ->label('الحالة')
                            ->options([
                                'debit' => 'مدين (رصيد موجب)',
                                'credit' => 'دائن (رصيد سالب)',
                                'zero' => 'متوازن (صفر)',
                            ])
                            ->native(false),
                    ])
                    ->query(function ($query, array $data) {
                        if (!isset($data['status'])) {
                            return $query;
                        }

                        return $query->when(
                            $data['status'] === 'debit',
                            fn ($q) => $q->where('current_balance', '>', 0),
                            fn ($q) => $query->when(
                                $data['status'] === 'credit',
                                fn ($q2) => $q2->where('current_balance', '<', 0),
                                fn ($q2) => $q2->where('current_balance', '=', 0)
                            )
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->slideOver(),
                Tables\Actions\Action::make('statement')
                    ->label('كشف حساب')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->url(fn (Partner $record) => route('filament.admin.pages.reports-hub', [
                        'report' => 'partner_statement',
                        'partner_id' => $record->id,
                    ]))
                    ->openUrlInNewTab(false)
                    ->visible(fn () => auth()->user()?->can('page_PartnerStatement')),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPartners::route('/'),
            'create' => Pages\CreatePartner::route('/create'),
            'edit' => Pages\EditPartner::route('/{record}/edit'),
        ];
    }
}
