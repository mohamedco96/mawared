<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $cluster = \App\Filament\Clusters\SystemSettings::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationLabel = 'المستخدمين';

    protected static ?string $modelLabel = 'مستخدم';

    protected static ?string $pluralModelLabel = 'المستخدمين';

    protected static ?int $navigationSort = 1;

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
                        Forms\Components\TextInput::make('email')
                            ->label('البريد الإلكتروني')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->default(null),
                        Forms\Components\TextInput::make('password')
                            ->label('كلمة المرور')
                            ->password()
                            ->dehydrated(fn ($state) => filled($state))
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->required(fn (string $context): bool => $context === 'create')
                            ->minLength(8)
                            ->regex('/^(?=.*[A-Za-z])(?=.*\d).+$/')
                            ->validationMessages([
                                'regex' => 'يجب أن تحتوي كلمة المرور على أحرف وأرقام',
                            ])
                            ->maxLength(255)
                            ->default(null),
                        Forms\Components\Select::make('roles')
                            ->label('الدور')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->native(false),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('معلومات الموظف')
                    ->schema([
                        Forms\Components\TextInput::make('national_id')
                            ->label('الهوية الوطنية')
                            ->numeric()
                            ->length(14)
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'numeric'])
                            ->validationMessages([
                                'length' => 'يجب أن يكون الرقم القومي 14 رقم بالضبط',
                            ]),
                        Forms\Components\Select::make('salary_type')
                            ->label('نوع الراتب')
                            ->options([
                                'daily' => 'يومي',
                                'monthly' => 'شهري',
                            ])
                            ->native(false),
                        Forms\Components\TextInput::make('salary_amount')
                            ->label('مبلغ الراتب')
                            ->numeric()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                            ->step(0.01),
                        Forms\Components\TextInput::make('advance_balance')
                            ->label('رصيد السلفة')
                            ->numeric()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                            ->step(0.01)
                            ->default(0),
                    ])
                    ->columns(4),
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
                Tables\Columns\TextColumn::make('email')
                    ->label('البريد الإلكتروني')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('national_id')
                    ->label('الهوية الوطنية')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('salary_type')
                    ->label('نوع الراتب')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === 'daily' ? 'يومي' : ($state === 'monthly' ? 'شهري' : '—'))
                    ->color(fn (?string $state): string => $state === 'daily' ? 'info' : 'success'),
                Tables\Columns\TextColumn::make('salary_amount')
                    ->label('الراتب')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                Tables\Columns\TextColumn::make('advance_balance')
                    ->label('رصيد السلفة')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('الأدوار')
                    ->badge()
                    ->separator(',')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('salary_type')
                    ->label('نوع الراتب')
                    ->options([
                        'daily' => 'يومي',
                        'monthly' => 'شهري',
                    ])
                    ->native(false),
                Tables\Filters\Filter::make('salary_amount')
                    ->label('مبلغ الراتب')
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
                            ->when($data['from'], fn ($q, $amount) => $q->where('salary_amount', '>=', $amount))
                            ->when($data['until'], fn ($q, $amount) => $q->where('salary_amount', '<=', $amount));
                    }),
            ], layout: FiltersLayout::Dropdown)
            ->actions([
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
