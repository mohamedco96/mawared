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

    protected static ?string $cluster = \App\Filament\Clusters\SystemSettings::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'سجل النشاطات';
    protected static ?string $modelLabel = 'نشاط';
    protected static ?string $pluralModelLabel = 'سجل النشاطات';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['subject', 'causer']))
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
                            'shareholder_capital' => 'رأس مال الشركاء',
                            'capital_deposit' => 'إيداع رأس مال',
                            'shareholder_investment' => 'استثمار الشركاء',
                            'financial_transaction' => 'معاملة مالية',
                            'fixed_asset' => 'أصل ثابت',
                            'warehouse_transfer' => 'نقل بين المخازن',
                            'stock_adjustment' => 'تسوية مخزون',
                            'expense' => 'مصروف',
                            'revenue' => 'إيراد',
                            'quotation' => 'عرض سعر',
                            'installment' => 'قسط',
                            default => $state,
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
                                    'shareholder_capital' => 'رأس مال الشركاء',
                                    'capital_deposit' => 'إيداع رأس مال',
                                    'shareholder_investment' => 'استثمار الشركاء',
                                    'financial_transaction' => 'معاملة مالية',
                                    'fixed_asset' => 'أصل ثابت',
                                    'warehouse_transfer' => 'نقل بين المخازن',
                                    'stock_adjustment' => 'تسوية مخزون',
                                    'expense' => 'مصروف',
                                    'revenue' => 'إيراد',
                                    'quotation' => 'عرض سعر',
                                    'installment' => 'قسط',
                                    default => $state,
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
                                Infolists\Components\KeyValueEntry::make('formatted_attributes')
                                    ->label('القيم الجديدة / الحالية')
                                    ->columnSpan(1)
                                    ->state(function ($record) {
                                        if (!$record->properties || !isset($record->properties['attributes'])) {
                                            return [];
                                        }

                                        return collect($record->properties['attributes'])->mapWithKeys(function ($value, $key) {
                                            $translatedKey = static::translateFieldName($key);
                                            $translatedValue = static::translateFieldValue($key, $value);
                                            return [$translatedKey => $translatedValue];
                                        })->toArray();
                                    })
                                    ->visible(fn ($record) =>
                                        $record->properties &&
                                        isset($record->properties['attributes']) &&
                                        !empty($record->properties['attributes'])
                                    ),

                                Infolists\Components\KeyValueEntry::make('formatted_old')
                                    ->label('القيم القديمة')
                                    ->columnSpan(1)
                                    ->state(function ($record) {
                                        if (!$record->properties || !isset($record->properties['old'])) {
                                            return [];
                                        }

                                        return collect($record->properties['old'])->mapWithKeys(function ($value, $key) {
                                            $translatedKey = static::translateFieldName($key);
                                            $translatedValue = static::translateFieldValue($key, $value);
                                            return [$translatedKey => $translatedValue];
                                        })->toArray();
                                    })
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

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_activity::log') ?? false;
    }

    protected static function getSubjectUrl($record): ?string
    {
        if (!$record->subject || !$record->subject_type) {
            return null;
        }

        $resourceMap = [
            'product' => \App\Filament\Resources\ProductResource::class,
            'sales_invoice' => \App\Filament\Resources\SalesInvoiceResource::class,
            'purchase_invoice' => \App\Filament\Resources\PurchaseInvoiceResource::class,
            'partner' => \App\Filament\Resources\PartnerResource::class,
            'user' => \App\Filament\Resources\UserResource::class,
            'fixed_asset' => \App\Filament\Resources\FixedAssetResource::class,
            'quotation' => \App\Filament\Resources\QuotationResource::class,
            'installment' => \App\Filament\Resources\InstallmentResource::class,
        ];

        $resourceClass = $resourceMap[$record->subject_type] ?? null;

        if (!$resourceClass || !class_exists($resourceClass)) {
            return null;
        }

        // Try 'view' first for invoices, then fallback to 'edit'
        try {
            if (in_array($record->subject_type, ['sales_invoice', 'purchase_invoice'])) {
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

    /**
     * Translate field names from English to Arabic
     */
    protected static function translateFieldName(string $fieldName): string
    {
        $translations = [
            // Common fields
            'id' => 'الرقم التعريفي',
            'name' => 'الاسم',
            'sku' => 'رمز المنتج',
            'barcode' => 'الباركود',
            'description' => 'الوصف',
            'notes' => 'ملاحظات',
            'created_at' => 'تاريخ الإنشاء',
            'updated_at' => 'تاريخ التحديث',
            'deleted_at' => 'تاريخ الحذف',
            'created_by' => 'أنشئ بواسطة',
            'updated_by' => 'حُدث بواسطة',

            // Product fields
            'category_id' => 'الفئة',
            'retail_price' => 'سعر قطاعي',
            'wholesale_price' => 'سعر الجملة',
            'avg_cost' => 'متوسط التكلفة',
            'min_stock' => 'الحد الأدنى للمخزون',
            'factor' => 'المعامل',
            'small_unit_id' => 'الوحدة الصغيرة',
            'large_unit_id' => 'الوحدة الكبيرة',
            'large_barcode' => 'الباركود (الوحدة الكبيرة)',
            'large_retail_price' => 'سعر قطاعي (الوحدة الكبيرة)',
            'large_wholesale_price' => 'سعر الجملة (الوحدة الكبيرة)',
            'is_visible_in_retail_catalog' => 'ظاهر في كتالوج قطاعي',
            'is_visible_in_wholesale_catalog' => 'ظاهر في كتالوج الجملة',
            'image' => 'الصورة',
            'images' => 'الصور',

            // Partner fields
            'type' => 'النوع',
            'phone' => 'الهاتف',
            'gov_id' => 'الهوية الوطنية',
            'region' => 'المنطقة',
            'current_balance' => 'الرصيد الحالي',
            'is_banned' => 'محظور',

            // Invoice fields
            'invoice_number' => 'رقم الفاتورة',
            'partner_id' => 'الشريك',
            'warehouse_id' => 'المخزن',
            'status' => 'الحالة',
            'payment_method' => 'طريقة الدفع',
            'subtotal' => 'المجموع الفرعي',
            'discount' => 'الخصم',
            'total' => 'الإجمالي',
            'paid_amount' => 'المبلغ المدفوع',
            'remaining_amount' => 'المبلغ المتبقي',
            'tax' => 'الضريبة',
            'tax_rate' => 'معدل الضريبة',

            // Stock fields
            'product_id' => 'المنتج',
            'quantity' => 'الكمية',
            'cost_at_time' => 'التكلفة',
            'unit_type' => 'نوع الوحدة',
            'unit_price' => 'سعر الوحدة',
            'unit_cost' => 'تكلفة الوحدة',
            'net_unit_price' => 'صافي سعر الوحدة',
            'net_unit_cost' => 'صافي تكلفة الوحدة',

            // Treasury fields
            'treasury_id' => 'الخزينة',
            'amount' => 'المبلغ',
            'reference_type' => 'نوع المرجع',
            'reference_id' => 'رقم المرجع',
            'balance' => 'الرصيد',

            // User fields
            'email' => 'البريد الإلكتروني',
            'password' => 'كلمة المرور',
            'role' => 'الدور',
            'is_active' => 'نشط',

            // Return fields
            'return_number' => 'رقم المرتجع',
            'sales_invoice_id' => 'فاتورة البيع',
            'purchase_invoice_id' => 'فاتورة الشراء',

            // Quotation fields
            'quotation_number' => 'رقم عرض السعر',
            'customer_name' => 'اسم العميل',
            'guest_name' => 'اسم الزائر',
            'guest_phone' => 'هاتف الزائر',
            'pricing_type' => 'نوع التسعير',
            'valid_until' => 'صالح حتى',
            'converted_to_invoice_id' => 'حُول إلى فاتورة',
            'is_guest' => 'زائر',

            // Installment fields
            'installment_number' => 'رقم القسط',
            'due_date' => 'تاريخ الاستحقاق',
            'paid_at' => 'تاريخ الدفع',
            'payment_date' => 'تاريخ الدفع',

            // Fixed Asset fields
            'asset_number' => 'رقم الأصل',
            'purchase_date' => 'تاريخ الشراء',
            'purchase_cost' => 'تكلفة الشراء',
            'useful_life_years' => 'العمر الإنتاجي (سنوات)',
            'salvage_value' => 'قيمة الخردة',
            'depreciation_method' => 'طريقة الاستهلاك',
            'current_value' => 'القيمة الحالية',

            // Expense fields
            'expense_number' => 'رقم المصروف',
            'category' => 'الفئة',
            'date' => 'التاريخ',

            // Revenue fields
            'revenue_number' => 'رقم الإيراد',
            'source' => 'المصدر',

            // Warehouse fields
            'address' => 'العنوان',
            'is_active' => 'نشط',

            // Transfer fields
            'transfer_number' => 'رقم النقل',
            'from_warehouse_id' => 'من مخزن',
            'to_warehouse_id' => 'إلى مخزن',

            // Adjustment fields
            'adjustment_number' => 'رقم التسوية',
            'reason' => 'السبب',

            // Unit fields
            'abbreviation' => 'الاختصار',

            // Employee fields
            'employee_id' => 'الموظف',
            'advance_balance' => 'رصيد السلف',
        ];

        return $translations[$fieldName] ?? $fieldName;
    }

    /**
     * Translate field values from English to Arabic
     */
    protected static function translateFieldValue(string $fieldName, $value)
    {
        // Handle null values
        if ($value === null) {
            return '—';
        }

        // Handle arrays
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        // Handle boolean values
        if (is_bool($value)) {
            return $value ? 'نعم' : 'لا';
        }

        // Handle specific field value translations
        $stringValue = (string) $value;

        // Boolean string values (0/1)
        if (in_array($fieldName, ['is_banned', 'is_active', 'is_guest', 'is_visible_in_retail_catalog', 'is_visible_in_wholesale_catalog'])) {
            if ($stringValue === '1' || $stringValue === 'true') {
                return 'نعم';
            } elseif ($stringValue === '0' || $stringValue === 'false') {
                return 'لا';
            }
        }

        // Status translations
        if (in_array($fieldName, ['status'])) {
            $statusTranslations = [
                'draft' => 'مسودة',
                'posted' => 'مؤكدة',
                'sent' => 'مرسل',
                'accepted' => 'مقبول',
                'converted' => 'محول',
                'rejected' => 'مرفوض',
                'expired' => 'منتهي',
                'pending' => 'قيد الانتظار',
                'completed' => 'مكتمل',
                'cancelled' => 'ملغي',
                'active' => 'نشط',
                'inactive' => 'غير نشط',
            ];
            return $statusTranslations[$stringValue] ?? $value;
        }

        // Type translations
        if (in_array($fieldName, ['type'])) {
            $typeTranslations = [
                'customer' => 'عميل',
                'supplier' => 'مورد',
                'shareholder' => 'شريك (مساهم)',
                'collection' => 'تحصيل',
                'payment' => 'دفع',
                'income' => 'إيراد',
                'expense' => 'مصروف',
                'capital_deposit' => 'إيداع رأس المال',
                'partner_drawing' => 'سحب شريك',
                'employee_advance' => 'سلفة موظف',
                'sale' => 'بيع',
                'purchase' => 'شراء',
                'sale_return' => 'مرتجع بيع',
                'purchase_return' => 'مرتجع شراء',
                'adjustment_in' => 'إضافة',
                'adjustment_out' => 'خصم',
                'transfer' => 'نقل',
            ];
            return $typeTranslations[$stringValue] ?? $value;
        }

        // Payment method translations
        if (in_array($fieldName, ['payment_method'])) {
            $paymentTranslations = [
                'cash' => 'نقدي',
                'credit' => 'آجل',
                'bank' => 'بنك',
                'check' => 'شيك',
                'transfer' => 'تحويل',
            ];
            return $paymentTranslations[$stringValue] ?? $value;
        }

        // Unit type translations
        if (in_array($fieldName, ['unit_type'])) {
            $unitTranslations = [
                'small' => 'صغيرة',
                'large' => 'كبيرة',
            ];
            return $unitTranslations[$stringValue] ?? $value;
        }

        // Reference type translations
        if (in_array($fieldName, ['reference_type'])) {
            $referenceTranslations = [
                'sales_invoice' => 'فاتورة بيع',
                'purchase_invoice' => 'فاتورة شراء',
                'sales_return' => 'مرتجع بيع',
                'purchase_return' => 'مرتجع شراء',
                'stock_adjustment' => 'تسوية',
                'warehouse_transfer' => 'نقل',
                'financial_transaction' => 'معاملة مالية',
                'quotation' => 'عرض سعر',
                'installment' => 'قسط',
            ];
            return $referenceTranslations[$stringValue] ?? $value;
        }

        // Pricing type translations
        if (in_array($fieldName, ['pricing_type'])) {
            $pricingTranslations = [
                'retail' => 'سعر قطاعي',
                'wholesale' => 'سعر الجملة',
                'manual' => 'يدوي',
                'custom' => 'مخصص',
            ];
            return $pricingTranslations[$stringValue] ?? $value;
        }

        // Depreciation method translations
        if (in_array($fieldName, ['depreciation_method'])) {
            $depreciationTranslations = [
                'straight_line' => 'القسط الثابت',
                'declining_balance' => 'الرصيد المتناقص',
                'sum_of_years' => 'مجموع أرقام السنوات',
            ];
            return $depreciationTranslations[$stringValue] ?? $value;
        }

        // Reason translations (for adjustments)
        if (in_array($fieldName, ['reason'])) {
            $reasonTranslations = [
                'damaged' => 'تالف',
                'lost' => 'مفقود',
                'expired' => 'منتهي الصلاحية',
                'count_adjustment' => 'تعديل جرد',
                'other' => 'أخرى',
            ];
            return $reasonTranslations[$stringValue] ?? $value;
        }

        // Category translations (for expenses/revenues)
        if (in_array($fieldName, ['category'])) {
            $categoryTranslations = [
                'operational' => 'تشغيلية',
                'administrative' => 'إدارية',
                'sales' => 'مبيعات',
                'marketing' => 'تسويقية',
                'maintenance' => 'صيانة',
                'utilities' => 'مرافق',
                'salaries' => 'رواتب',
                'rent' => 'إيجار',
                'other' => 'أخرى',
            ];
            return $categoryTranslations[$stringValue] ?? $value;
        }

        return $value;
    }
}
