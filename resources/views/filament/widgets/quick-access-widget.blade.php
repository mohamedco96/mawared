<div class="mb-8 w-full">
    {{-- Cards Container with Flexbox for Single Row Alignment --}}
    <div class="flex flex-row flex-nowrap gap-4 w-full overflow-x-auto pb-4 lg:pb-0">

        {{-- Inventory Card (Emerald to Teal) --}}
        <div
            class="flex-shrink-0 min-w-[280px] lg:min-w-0 lg:flex-1 bg-white rounded-2xl shadow-md overflow-hidden hover:shadow-xl transition-all duration-300 group border border-gray-100">
            <div class="h-[90px] p-4 relative flex items-center justify-between"
                style="background: linear-gradient(135deg, #10b981 0%, #0d9488 100%);">
                <span class="text-white font-bold text-lg">المخزون</span>
                <x-heroicon-o-cube
                    class="w-10 h-10 text-white opacity-90 group-hover:scale-110 transition-transform duration-300" />
            </div>
            <div class="p-4 space-y-2">
                <a href="{{ \App\Filament\Resources\StockAdjustmentResource::getUrl('create') }}"
                    class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 text-gray-600 transition-colors group/link">
                    <span class="text-sm font-medium group-hover/link:text-[#10b981]">جرد المخازن</span>
                    <x-heroicon-m-arrow-left
                        class="w-4 h-4 opacity-0 group-hover/link:opacity-100 transition-opacity text-[#10b981]" />
                </a>
                <a href="{{ \App\Filament\Resources\ProductResource::getUrl('create') }}"
                    class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 text-gray-600 transition-colors group/link">
                    <span class="text-sm font-medium group-hover/link:text-[#10b981]">إضافة صنف</span>
                    <x-heroicon-m-arrow-left
                        class="w-4 h-4 opacity-0 group-hover/link:opacity-100 transition-opacity text-[#10b981]" />
                </a>
            </div>
        </div>

        {{-- Treasury Card (Violet to Purple) --}}
        <div
            class="flex-shrink-0 min-w-[280px] lg:min-w-0 lg:flex-1 bg-white rounded-2xl shadow-md overflow-hidden hover:shadow-xl transition-all duration-300 group border border-gray-100">
            <div class="h-[90px] p-4 relative flex items-center justify-between"
                style="background: linear-gradient(135deg, #7c3aed 0%, #9333ea 100%);">
                <span class="text-white font-bold text-lg">الخزينة</span>
                <x-heroicon-o-banknotes
                    class="w-10 h-10 text-white opacity-90 group-hover:scale-110 transition-transform duration-300" />
            </div>
            <div class="p-4 space-y-2">
                <a href="{{ \App\Filament\Resources\TreasuryTransactionResource::getUrl('create') }}"
                    class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 text-gray-600 transition-colors group/link">
                    <span class="text-sm font-medium group-hover/link:text-[#7c3aed]">إيداع نقدية</span>
                    <x-heroicon-m-arrow-left
                        class="w-4 h-4 opacity-0 group-hover/link:opacity-100 transition-opacity text-[#7c3aed]" />
                </a>
                <a href="{{ \App\Filament\Resources\TreasuryTransactionResource::getUrl('create') }}"
                    class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 text-gray-600 transition-colors group/link">
                    <span class="text-sm font-medium group-hover/link:text-[#7c3aed]">صرف نقدية</span>
                    <x-heroicon-m-arrow-left
                        class="w-4 h-4 opacity-0 group-hover/link:opacity-100 transition-opacity text-[#7c3aed]" />
                </a>
            </div>
        </div>

        {{-- Purchasing Card (Orange) --}}
        <div
            class="flex-shrink-0 min-w-[280px] lg:min-w-0 lg:flex-1 bg-white rounded-2xl shadow-md overflow-hidden hover:shadow-xl transition-all duration-300 group border border-gray-100">
            <div class="h-[90px] p-4 relative flex items-center justify-between"
                style="background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);">
                <span class="text-white font-bold text-lg">المشتريات</span>
                <x-heroicon-o-shopping-bag
                    class="w-10 h-10 text-white opacity-90 group-hover:scale-110 transition-transform duration-300" />
            </div>
            <div class="p-4 space-y-2">
                <a href="{{ \App\Filament\Resources\PurchaseInvoiceResource::getUrl('create') }}"
                    class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 text-gray-600 transition-colors group/link">
                    <span class="text-sm font-medium group-hover/link:text-[#f97316]">فاتورة شراء</span>
                    <x-heroicon-m-arrow-left
                        class="w-4 h-4 opacity-0 group-hover/link:opacity-100 transition-opacity text-[#f97316]" />
                </a>
                <a href="{{ \App\Filament\Resources\PurchaseReturnResource::getUrl('create') }}"
                    class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 text-gray-600 transition-colors group/link">
                    <span class="text-sm font-medium group-hover/link:text-[#f97316]">مردودات شراء</span>
                    <x-heroicon-m-arrow-left
                        class="w-4 h-4 opacity-0 group-hover/link:opacity-100 transition-opacity text-[#f97316]" />
                </a>
            </div>
        </div>

        {{-- Customers Card (Cyan) --}}
        <div
            class="flex-shrink-0 min-w-[280px] lg:min-w-0 lg:flex-1 bg-white rounded-2xl shadow-md overflow-hidden hover:shadow-xl transition-all duration-300 group border border-gray-100">
            <div class="h-[90px] p-4 relative flex items-center justify-between"
                style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);">
                <span class="text-white font-bold text-lg">العملاء</span>
                <x-heroicon-o-user-group
                    class="w-10 h-10 text-white opacity-90 group-hover:scale-110 transition-transform duration-300" />
            </div>
            <div class="p-4 space-y-2">
                <a href="{{ \App\Filament\Pages\PartnerStatement::getUrl() }}"
                    class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 text-gray-600 transition-colors group/link">
                    <span class="text-sm font-medium group-hover/link:text-[#06b6d4]">كشف حساب</span>
                    <x-heroicon-m-arrow-left
                        class="w-4 h-4 opacity-0 group-hover/link:opacity-100 transition-opacity text-[#06b6d4]" />
                </a>
                <a href="{{ \App\Filament\Resources\PartnerResource::getUrl('create') }}"
                    class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 text-gray-600 transition-colors group/link">
                    <span class="text-sm font-medium group-hover/link:text-[#06b6d4]">إضافة عميل</span>
                    <x-heroicon-m-arrow-left
                        class="w-4 h-4 opacity-0 group-hover/link:opacity-100 transition-opacity text-[#06b6d4]" />
                </a>
            </div>
        </div>

        {{-- Sales Card (Indigo to Blue) --}}
        <div
            class="flex-shrink-0 min-w-[280px] lg:min-w-0 lg:flex-1 bg-white rounded-2xl shadow-md overflow-hidden hover:shadow-xl transition-all duration-300 group border border-gray-100">
            <div class="h-[90px] p-4 relative flex items-center justify-between"
                style="background: linear-gradient(135deg, #4f46e5 0%, #2563eb 100%);">
                <span class="text-white font-bold text-lg">المبيعات</span>
                <x-heroicon-o-shopping-cart
                    class="w-10 h-10 text-white opacity-90 group-hover:scale-110 transition-transform duration-300" />
            </div>
            <div class="p-4 space-y-2">
                <a href="{{ \App\Filament\Resources\SalesInvoiceResource::getUrl('create') }}"
                    class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 text-gray-600 transition-colors group/link">
                    <span class="text-sm font-medium group-hover/link:text-[#4f46e5]">فاتورة بيع</span>
                    <x-heroicon-m-arrow-left
                        class="w-4 h-4 opacity-0 group-hover/link:opacity-100 transition-opacity text-[#4f46e5]" />
                </a>
                <a href="{{ \App\Filament\Resources\SalesReturnResource::getUrl('create') }}"
                    class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 text-gray-600 transition-colors group/link">
                    <span class="text-sm font-medium group-hover/link:text-[#4f46e5]">مرتجع بيع</span>
                    <x-heroicon-m-arrow-left
                        class="w-4 h-4 opacity-0 group-hover/link:opacity-100 transition-opacity text-[#4f46e5]" />
                </a>
            </div>
        </div>

    </div>
</div>
