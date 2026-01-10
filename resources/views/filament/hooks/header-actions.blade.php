{{-- Mobile: Icon buttons only --}}
<div class="flex items-center gap-x-2 md:hidden">
    <x-filament::icon-button :href="\App\Filament\Resources\PurchaseInvoiceResource::getUrl('create')" icon="heroicon-o-shopping-cart" color="warning" size="sm"
        tooltip="اضافة فاتورة شراء" tag="a" />


    <x-filament::icon-button :href="\App\Filament\Resources\SalesInvoiceResource::getUrl('create')" icon="heroicon-o-shopping-bag" color="primary" size="sm"
        tooltip="اضافة فاتورة مبيعات" tag="a" />

    <x-filament::icon-button :href="\App\Filament\Resources\ExpenseResource::getUrl('create')" icon="heroicon-o-banknotes" color="danger" size="sm"
        tooltip="اضافة مصروف" tag="a" />

    <x-filament::icon-button :href="\App\Filament\Resources\TreasuryTransactionResource::getUrl('create')" icon="heroicon-o-arrows-right-left" color="success" size="sm"
        tooltip="اضافة معاملة مالية" tag="a" />
</div>

{{-- Desktop/Tablet: Icon + Label buttons --}}
<div class="hidden md:flex items-center gap-x-2">
    <x-filament::button :href="\App\Filament\Resources\PurchaseInvoiceResource::getUrl('create')" icon="heroicon-o-shopping-cart" color="warning" size="sm" tag="a">
        فاتورة شراء
    </x-filament::button>

    <x-filament::button :href="\App\Filament\Resources\SalesInvoiceResource::getUrl('create')" icon="heroicon-o-shopping-bag" color="primary" size="sm" tag="a">
        فاتورة مبيعات
    </x-filament::button>

    <x-filament::button :href="\App\Filament\Resources\ExpenseResource::getUrl('create')" icon="heroicon-o-banknotes" color="danger" size="sm" tag="a">
        مصروف
    </x-filament::button>

    <x-filament::button :href="\App\Filament\Resources\TreasuryTransactionResource::getUrl('create')" icon="heroicon-o-arrows-right-left" color="success" size="sm"
        tag="a">
        معاملة مالية
    </x-filament::button>
</div>
