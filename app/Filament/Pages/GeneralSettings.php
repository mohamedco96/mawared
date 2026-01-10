<?php

namespace App\Filament\Pages;

use App\Settings\CompanySettings;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class GeneralSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $view = 'filament.pages.general-settings';

    protected static ?string $navigationLabel = 'إعدادات الشركة';

    protected static ?string $title = 'إعدادات الشركة';

    protected static ?string $navigationGroup = 'إعدادات النظام';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('page_GeneralSettings') ?? false;
    }

    public ?array $data = [];

    public function mount(): void
    {
        $settings = app(CompanySettings::class);

        $this->form->fill([
            'company_name' => $settings->company_name,
            'company_name_english' => $settings->company_name_english,
            'company_address' => $settings->company_address,
            'company_phone' => $settings->company_phone,
            'company_email' => $settings->company_email,
            'company_tax_number' => $settings->company_tax_number,
            'company_commercial_register' => $settings->company_commercial_register,
            'logo' => $settings->logo,
            'currency' => $settings->currency,
            'currency_symbol' => $settings->currency_symbol,
            'low_stock_threshold' => $settings->low_stock_threshold,
            'invoice_prefix_sales' => $settings->invoice_prefix_sales,
            'invoice_prefix_purchase' => $settings->invoice_prefix_purchase,
            'return_prefix_sales' => $settings->return_prefix_sales,
            'return_prefix_purchase' => $settings->return_prefix_purchase,
            'transfer_prefix' => $settings->transfer_prefix,
            'enable_multi_warehouse' => $settings->enable_multi_warehouse,
            'enable_multi_treasury' => $settings->enable_multi_treasury,
            'default_payment_terms_days' => $settings->default_payment_terms_days,
            'allow_negative_stock' => $settings->allow_negative_stock,
            'auto_approve_stock_adjustments' => $settings->auto_approve_stock_adjustments,
            'business_whatsapp_number' => $settings->business_whatsapp_number,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('معلومات الشركة')
                    ->description('البيانات الأساسية للشركة')
                    ->schema([
                        Forms\Components\TextInput::make('company_name')
                            ->label('اسم الشركة (عربي)')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('company_name_english')
                            ->label('اسم الشركة (إنجليزي)')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('company_address')
                            ->label('العنوان')
                            ->required()
                            ->rows(2),
                        Forms\Components\TextInput::make('company_phone')
                            ->label('الهاتف')
                            ->tel()
                            ->required(),
                        Forms\Components\TextInput::make('company_email')
                            ->label('البريد الإلكتروني')
                            ->email()
                            ->required(),
                        Forms\Components\TextInput::make('company_tax_number')
                            ->label('الرقم الضريبي'),
                        Forms\Components\TextInput::make('company_commercial_register')
                            ->label('السجل التجاري'),
                        Forms\Components\FileUpload::make('logo')
                            ->label('شعار الشركة')
                            ->image()
                            ->directory('company')
                            ->disk('public')
                            ->imageEditor()
                            ->maxSize(2048)
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/jpg'])
                            ->helperText('سيتم عرض الشعار في الفواتير المطبوعة (الحد الأقصى: 2 ميجابايت)')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('إعدادات العملة')
                    ->schema([
                        Forms\Components\TextInput::make('currency')
                            ->label('العملة')
                            ->default('EGP')
                            ->required(),
                        Forms\Components\TextInput::make('currency_symbol')
                            ->label('رمز العملة')
                            ->default('ج.م')
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('إعدادات الكتالوج الرقمي')
                    ->description('إعدادات متجر واتساب والكتالوج العام')
                    ->schema([
                        Forms\Components\TextInput::make('business_whatsapp_number')
                            ->label('رقم واتساب الأعمال')
                            ->helperText('أدخل رقم الهاتف بالصيغة الدولية (مثال: 201234567890). سيستخدم لتلقي طلبات الكتالوج الرقمي')
                            ->placeholder('201234567890')
                            ->tel()
                            ->maxLength(20)
                            ->prefix('+')
                            ->extraInputAttributes(['dir' => 'ltr'])
                            ->columnSpanFull(),
                        Forms\Components\Placeholder::make('showroom_qr_notice')
                            ->label('رموز QR للكتالوج')
                            ->content('يمكنك إنشاء رموز QR للكتالوج من صفحة المنتجات. ستحتاج إلى إدخال رقم واتساب أولاً.')
                            ->columnSpanFull(),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('إعدادات المستندات')
                    ->schema([
                        Forms\Components\TextInput::make('invoice_prefix_sales')
                            ->label('بادئة فاتورة البيع')
                            ->default('INV-SALE-')
                            ->required(),
                        Forms\Components\TextInput::make('invoice_prefix_purchase')
                            ->label('بادئة فاتورة الشراء')
                            ->default('INV-PUR-')
                            ->required(),
                        Forms\Components\TextInput::make('return_prefix_sales')
                            ->label('بادئة مرتجع البيع')
                            ->default('RET-SALE-')
                            ->required(),
                        Forms\Components\TextInput::make('return_prefix_purchase')
                            ->label('بادئة مرتجع الشراء')
                            ->default('RET-PUR-')
                            ->required(),
                        Forms\Components\TextInput::make('transfer_prefix')
                            ->label('بادئة التحويل')
                            ->default('TRF-')
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('إعدادات النظام')
                    ->schema([
                        Forms\Components\TextInput::make('low_stock_threshold')
                            ->label('حد المخزون المنخفض')
                            ->numeric()
                            ->default(10)
                            ->required(),
                        Forms\Components\TextInput::make('default_payment_terms_days')
                            ->label('مدة السداد الافتراضية (أيام)')
                            ->numeric()
                            ->default(30)
                            ->required(),
                        Forms\Components\Toggle::make('enable_multi_warehouse')
                            ->label('تفعيل تعدد المخازن')
                            ->default(true),
                        Forms\Components\Toggle::make('enable_multi_treasury')
                            ->label('تفعيل تعدد الخزائن')
                            ->default(true),
                        Forms\Components\Toggle::make('allow_negative_stock')
                            ->label('السماح بمخزون سالب')
                            ->default(false),
                        Forms\Components\Toggle::make('auto_approve_stock_adjustments')
                            ->label('الموافقة التلقائية على تعديلات المخزون')
                            ->default(false),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('الأصول الثابتة')
                    ->description('تم نقل إدارة الأصول الثابتة إلى وحدة منفصلة')
                    ->schema([
                        Forms\Components\Placeholder::make('fixed_assets_notice')
                            ->label('')
                            ->content('لإدارة الأصول الثابتة، يرجى الانتقال إلى قسم "الأصول الثابتة" في قائمة الإدارة. يمكنك الآن تسجيل كل أصل بشكل منفصل مع تفاصيله وربطه بالخزينة.')
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $settings = app(CompanySettings::class);

        $settings->company_name = $data['company_name'];
        $settings->company_name_english = $data['company_name_english'];
        $settings->company_address = $data['company_address'];
        $settings->company_phone = $data['company_phone'];
        $settings->company_email = $data['company_email'];
        $settings->company_tax_number = $data['company_tax_number'] ?? null;
        $settings->company_commercial_register = $data['company_commercial_register'] ?? null;
        $settings->logo = $data['logo'] ?? '';
        $settings->currency = $data['currency'];
        $settings->currency_symbol = $data['currency_symbol'];
        $settings->low_stock_threshold = $data['low_stock_threshold'];
        $settings->invoice_prefix_sales = $data['invoice_prefix_sales'];
        $settings->invoice_prefix_purchase = $data['invoice_prefix_purchase'];
        $settings->return_prefix_sales = $data['return_prefix_sales'];
        $settings->return_prefix_purchase = $data['return_prefix_purchase'];
        $settings->transfer_prefix = $data['transfer_prefix'];
        $settings->enable_multi_warehouse = $data['enable_multi_warehouse'];
        $settings->enable_multi_treasury = $data['enable_multi_treasury'];
        $settings->default_payment_terms_days = $data['default_payment_terms_days'];
        $settings->allow_negative_stock = $data['allow_negative_stock'];
        $settings->auto_approve_stock_adjustments = $data['auto_approve_stock_adjustments'];
        $settings->business_whatsapp_number = $data['business_whatsapp_number'] ?? null;

        $settings->save();

        Notification::make()
            ->success()
            ->title('تم حفظ الإعدادات بنجاح')
            ->send();
    }
}

