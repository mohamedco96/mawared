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
                            ->columnSpanFull(),
                        Forms\Components\Select::make('type')
                            ->label('النوع')
                            ->options([
                                'customer' => 'عميل',
                                'supplier' => 'مورد',
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
                            ->prefix('ر.س')
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
                    ->formatStateUsing(fn (string $state): string => $state === 'customer' ? 'عميل' : 'مورد')
                    ->color(fn (string $state): string => $state === 'customer' ? 'success' : 'info'),
                Tables\Columns\TextColumn::make('phone')
                    ->label('الهاتف')
                    ->searchable(),
                Tables\Columns\TextColumn::make('current_balance')
                    ->label('الرصيد')
                    ->money('SAR')
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
                    ])
                    ->native(false),
                Tables\Filters\TernaryFilter::make('is_banned')
                    ->label('محظور'),
            ])
            ->actions([
                Tables\Actions\Action::make('statement')
                    ->label('كشف حساب')
                    ->icon('heroicon-o-document-text')
                    ->modalHeading(fn (Partner $record) => "كشف حساب - {$record->name}")
                    ->modalContent(function (Partner $record) {
                        $transactions = $record->treasuryTransactions()
                            ->with('treasury')
                            ->orderBy('created_at', 'desc')
                            ->limit(100)
                            ->get();
                        
                        $html = '<div class="space-y-4">';
                        $html .= '<div class="text-lg font-semibold">الرصيد الحالي: ' . number_format($record->current_balance, 2) . ' ر.س</div>';
                        $html .= '<table class="w-full text-sm">';
                        $html .= '<thead><tr class="border-b"><th class="text-right p-2">التاريخ</th><th class="text-right p-2">النوع</th><th class="text-right p-2">المبلغ</th><th class="text-right p-2">الوصف</th></tr></thead>';
                        $html .= '<tbody>';
                        foreach ($transactions as $transaction) {
                            $typeLabel = match($transaction->type) {
                                'collection' => 'تحصيل',
                                'payment' => 'دفع',
                                'income' => 'إيراد',
                                'expense' => 'مصروف',
                                default => $transaction->type,
                            };
                            $html .= '<tr class="border-b">';
                            $html .= '<td class="p-2">' . $transaction->created_at->format('Y-m-d H:i') . '</td>';
                            $html .= '<td class="p-2">' . $typeLabel . '</td>';
                            $html .= '<td class="p-2 ' . ($transaction->amount >= 0 ? 'text-green-600' : 'text-red-600') . '">' . number_format($transaction->amount, 2) . ' ر.س</td>';
                            $html .= '<td class="p-2">' . $transaction->description . '</td>';
                            $html .= '</tr>';
                        }
                        $html .= '</tbody></table>';
                        $html .= '</div>';
                        
                        return new \Illuminate\Support\HtmlString($html);
                    })
                    ->modalWidth('7xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('إغلاق'),
                Tables\Actions\EditAction::make(),
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
