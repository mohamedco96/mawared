<?php

namespace App\Filament\Resources\QuotationResource\Pages;

use App\Filament\Resources\QuotationResource;
use App\Models\Partner;
use App\Models\SalesInvoice;
use App\Models\Warehouse;
use App\Services\StockService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;

class ViewQuotation extends ViewRecord
{
    protected static string $resource = QuotationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Copy Link Action
            Actions\Action::make('copy_link')
                ->label('نسخ رابط العرض')
                ->icon('heroicon-o-link')
                ->color('info')
                ->action(function () {
                    $url = $this->record->getPublicUrl();
                    Notification::make()
                        ->success()
                        ->title('تم نسخ الرابط')
                        ->body($url)
                        ->send();
                })
                ->extraAttributes([
                    'x-on:click' => "\$event.preventDefault(); navigator.clipboard.writeText('{$this->record->getPublicUrl()}').then(() => { new FilamentNotification().success().title('تم نسخ الرابط').send(); });",
                ]),

            // A4 Print Action
            Actions\Action::make('print_a4')
                ->label('طباعة (A4)')
                ->icon('heroicon-o-document-text')
                ->url(fn () => route('quotations.public.pdf', [
                    'token' => $this->record->public_token,
                    'format' => 'a4',
                ]))
                ->openUrlInNewTab()
                ->color('primary'),

            // Thermal Print Action
            Actions\Action::make('print_thermal')
                ->label('طباعة (حراري)')
                ->icon('heroicon-o-receipt-percent')
                ->url(fn () => route('quotations.public.pdf', [
                    'token' => $this->record->public_token,
                    'format' => 'thermal',
                ]))
                ->openUrlInNewTab()
                ->color('success'),

            // Send via WhatsApp Action
            Actions\Action::make('send_whatsapp')
                ->label('إرسال عبر واتساب')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('success')
                ->url(fn () => $this->record->getWhatsAppUrl())
                ->openUrlInNewTab()
                ->visible(fn () => $this->record->status !== 'converted'),

            // Convert to Invoice Action
            Actions\Action::make('convert_to_invoice')
                ->label('تحويل إلى فاتورة')
                ->icon('heroicon-o-document-duplicate')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('تحويل عرض السعر إلى فاتورة مبيعات')
                ->modalDescription(fn () => $this->record->partner_id
                        ? 'سيتم إنشاء فاتورة مبيعات جديدة بحالة مسودة'
                        : 'عرض السعر لعميل غير مسجل. سيتم إنشاء شريك جديد أولاً.'
                )
                ->form(function () {
                    $baseFields = [
                        Forms\Components\Select::make('warehouse_id')
                            ->label('المستودع')
                            ->options(Warehouse::where('is_active', true)->pluck('name', 'id'))
                            ->required()
                            ->native(false)
                            ->preload()
                            ->searchable()
                            ->default(fn () => Warehouse::where('is_active', true)->first()?->id ?? Warehouse::first()?->id),
                        Forms\Components\Select::make('payment_method')
                            ->label('طريقة الدفع')
                            ->options(['cash' => 'نقدي', 'credit' => 'آجل'])
                            ->default('credit')
                            ->required()
                            ->native(false),
                    ];

                    // If guest quotation, add partner creation fields
                    if (! $this->record->partner_id) {
                        return array_merge([
                            Forms\Components\Section::make('إنشاء شريك جديد')
                                ->description('سيتم إنشاء شريك تلقائياً من بيانات العميل الضيف')
                                ->schema([
                                    Forms\Components\TextInput::make('partner_name')
                                        ->label('اسم الشريك')
                                        ->default($this->record->guest_name)
                                        ->required()
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('partner_phone')
                                        ->label('رقم الهاتف')
                                        ->default($this->record->guest_phone)
                                        ->required()
                                        ->tel()
                                        ->maxLength(20),
                                    Forms\Components\Select::make('partner_type')
                                        ->label('نوع الشريك')
                                        ->options([
                                            'customer' => 'عميل',
                                            'supplier' => 'مورد',
                                        ])
                                        ->default('customer')
                                        ->required()
                                        ->native(false),
                                    Forms\Components\TextInput::make('partner_region')
                                        ->label('المنطقة')
                                        ->maxLength(255)
                                        ->nullable(),
                                ])->columns(2),
                        ], $baseFields);
                    }

                    return $baseFields;
                })
                ->action(function (array $data, Actions\Action $action) {
                    // Validate stock availability for all items
                    $stockService = app(StockService::class);

                    $this->record->loadMissing('items.product');

                    foreach ($this->record->items as $item) {
                        $product = $item->product;
                        if (! $product) {
                            Notification::make()
                                ->danger()
                                ->title("المنتج '{$item->product_name}' غير موجود.")
                                ->send();

                            $action->halt();
                        }

                        // Check current stock
                        $requiredStock = $stockService->convertToBaseUnit($product, $item->quantity, $item->unit_type);
                        $validation = $stockService->getStockValidationMessage(
                            $data['warehouse_id'],
                            $item->product_id,
                            $requiredStock,
                            $item->unit_type
                        );

                        if (! $validation['is_available']) {
                            Notification::make()
                                ->danger()
                                ->title($validation['message'])
                                ->send();

                            $action->halt();
                        }
                    }

                    return DB::transaction(function () use ($data) {
                        $partnerId = $this->record->partner_id;

                        // Create partner if guest quotation
                        if (! $partnerId) {
                            $partner = Partner::create([
                                'name' => $data['partner_name'],
                                'phone' => $data['partner_phone'],
                                'type' => $data['partner_type'] ?? 'customer',
                                'region' => $data['partner_region'] ?? null,
                                'is_banned' => false,
                                'current_balance' => 0,
                            ]);
                            $partnerId = $partner->id;

                            // Update quotation with new partner
                            $this->record->update(['partner_id' => $partnerId]);

                            activity()
                                ->performedOn($this->record)
                                ->log("تم إنشاء شريك جديد: {$partner->name} من عرض السعر");
                        }

                        // Create sales invoice
                        $invoice = SalesInvoice::create([
                            'invoice_number' => 'SI-'.now()->format('Ymd').'-'.\Illuminate\Support\Str::random(6),
                            'warehouse_id' => $data['warehouse_id'],
                            'partner_id' => $partnerId,
                            'payment_method' => $data['payment_method'],
                            'status' => 'draft',
                            'discount_type' => $this->record->discount_type ?? 'fixed',
                            'discount_value' => $this->record->discount_value ?? 0,
                            'subtotal' => $this->record->subtotal,
                            'discount' => $this->record->discount ?? 0,
                            'total' => $this->record->total,
                            'paid_amount' => 0,
                            'remaining_amount' => $this->record->total,
                            'notes' => "محول من عرض السعر: {$this->record->quotation_number}\n".
                                      ($this->record->notes ?? ''),
                        ]);

                        // Copy items with quotation prices (snapshot)
                        foreach ($this->record->items as $item) {
                            $invoice->items()->create([
                                'product_id' => $item->product_id,
                                'unit_type' => $item->unit_type,
                                'quantity' => $item->quantity,
                                'unit_price' => $item->unit_price, // Use quotation price
                                'discount' => $item->discount,
                                'total' => $item->total,
                            ]);
                        }

                        // Update quotation
                        $this->record->update([
                            'status' => 'converted',
                            'converted_invoice_id' => $invoice->id,
                        ]);

                        // Log activity
                        activity()
                            ->performedOn($this->record)
                            ->log("تم تحويل عرض السعر إلى فاتورة مبيعات رقم: {$invoice->invoice_number}");

                        // Success notification
                        Notification::make()
                            ->success()
                            ->title('تم التحويل بنجاح')
                            ->body("رقم الفاتورة: {$invoice->invoice_number}")
                            ->send();

                        // Redirect to edit invoice
                        return redirect()->route('filament.admin.resources.sales-invoices.edit', $invoice);
                    });
                })
                ->visible(fn () => $this->record->canBeConverted() &&
                    ! $this->record->isExpired()
                ),

            Actions\EditAction::make()
                ->visible(fn () => $this->record->canBeEdited()),
        ];
    }
}
