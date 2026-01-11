<div class="space-y-4">
    {{-- Product Summary --}}
    <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
        <h3 class="text-lg font-semibold mb-2">{{ $product->name }}</h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
            @if($canViewCost)
                <div>
                    <span class="text-gray-600 dark:text-gray-400">متوسط التكلفة:</span>
                    <span class="font-bold text-green-600 dark:text-green-400">{{ number_format($product->avg_cost ?? 0, 2) }} ج.م</span>
                </div>
            @endif
            <div>
                <span class="text-gray-600 dark:text-gray-400">سعر البيع (قطاعي):</span>
                <span class="font-bold text-blue-600 dark:text-blue-400">{{ number_format($product->retail_price, 2) }} ج.م</span>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <div x-data="{ activeTab: 'purchases' }" class="space-y-4">
        {{-- Tab Headers --}}
        <div class="flex border-b border-gray-200 dark:border-gray-700">
            <button
                type="button"
                @click.prevent="activeTab = 'purchases'"
                :class="activeTab === 'purchases' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'"
                class="px-4 py-2 border-b-2 font-medium text-sm transition-colors"
            >
                سجل الشراء ({{ $purchases->count() }})
            </button>
            <button
                type="button"
                @click.prevent="activeTab = 'sales'"
                :class="activeTab === 'sales' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'"
                class="px-4 py-2 border-b-2 font-medium text-sm transition-colors"
            >
                سجل المبيعات ({{ $sales->count() }})
            </button>
        </div>

        {{-- Purchases Tab --}}
        <div x-show="activeTab === 'purchases'" x-transition class="space-y-2">
            @if($purchases->count() > 0 && $canViewCost)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead>
                            <tr class="bg-gray-100 dark:bg-gray-800">
                                <th class="p-2 text-center border border-gray-300 dark:border-gray-600">التاريخ</th>
                                <th class="p-2 text-center border border-gray-300 dark:border-gray-600">المورد</th>
                                <th class="p-2 text-center border border-gray-300 dark:border-gray-600">الكمية</th>
                                <th class="p-2 text-center border border-gray-300 dark:border-gray-600">سعر الوحدة</th>
                                <th class="p-2 text-center border border-gray-300 dark:border-gray-600">الإجمالي</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($purchases as $purchase)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                    <td class="p-2 text-center border border-gray-300 dark:border-gray-600">
                                        {{ $purchase->created_at->format('Y-m-d') }}
                                    </td>
                                    <td class="p-2 text-center border border-gray-300 dark:border-gray-600">
                                        {{ $purchase->purchaseInvoice->partner->name ?? 'غير محدد' }}
                                    </td>
                                    <td class="p-2 text-center border border-gray-300 dark:border-gray-600">
                                        {{ $purchase->quantity }} {{ $purchase->unit_type === 'large' ? 'كبيرة' : 'صغيرة' }}
                                    </td>
                                    <td class="p-2 text-center border border-gray-300 dark:border-gray-600 font-semibold text-green-600 dark:text-green-400">
                                        {{ number_format($purchase->unit_cost, 2) }}
                                    </td>
                                    <td class="p-2 text-center border border-gray-300 dark:border-gray-600">
                                        {{ number_format($purchase->total, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @elseif(!$canViewCost)
                <div class="text-center py-4 text-gray-500 dark:text-gray-400">
                    <div class="flex items-center justify-center gap-2">
                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <p class="text-sm">ليس لديك صلاحية لعرض أسعار الشراء</p>
                    </div>
                </div>
            @else
                <div class="text-center py-4 text-gray-500 dark:text-gray-400">
                    <div class="flex items-center justify-center gap-2">
                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                        </svg>
                        <p class="text-sm">لا توجد سجلات شراء لهذا المنتج</p>
                    </div>
                </div>
            @endif
        </div>

        {{-- Sales Tab --}}
        <div x-show="activeTab === 'sales'" x-transition class="space-y-2">
            @if($sales->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead>
                            <tr class="bg-gray-100 dark:bg-gray-800">
                                <th class="p-2 text-center border border-gray-300 dark:border-gray-600">التاريخ</th>
                                <th class="p-2 text-center border border-gray-300 dark:border-gray-600">العميل</th>
                                <th class="p-2 text-center border border-gray-300 dark:border-gray-600">الكمية</th>
                                <th class="p-2 text-center border border-gray-300 dark:border-gray-600">سعر الوحدة</th>
                                <th class="p-2 text-center border border-gray-300 dark:border-gray-600">الإجمالي</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sales as $sale)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                    <td class="p-2 text-center border border-gray-300 dark:border-gray-600">
                                        {{ $sale->created_at->format('Y-m-d') }}
                                    </td>
                                    <td class="p-2 text-center border border-gray-300 dark:border-gray-600">
                                        {{ $sale->salesInvoice->partner->name ?? 'غير محدد' }}
                                    </td>
                                    <td class="p-2 text-center border border-gray-300 dark:border-gray-600">
                                        {{ $sale->quantity }} {{ $sale->unit_type === 'large' ? 'كبيرة' : 'صغيرة' }}
                                    </td>
                                    <td class="p-2 text-center border border-gray-300 dark:border-gray-600 font-semibold text-blue-600 dark:text-blue-400">
                                        {{ number_format($sale->unit_price, 2) }}
                                    </td>
                                    <td class="p-2 text-center border border-gray-300 dark:border-gray-600">
                                        {{ number_format($sale->total, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-4 text-gray-500 dark:text-gray-400">
                    <div class="flex items-center justify-center gap-2">
                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                        <p class="text-sm">لا توجد سجلات مبيعات لهذا المنتج</p>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Additional Info --}}
    @if($canViewCost && $purchases->count() > 0 && $sales->count() > 0)
        <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 p-4 border border-blue-200 dark:border-blue-800">
            <h4 class="font-semibold text-blue-900 dark:text-blue-300 mb-2">تحليل الربحية</h4>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="text-blue-700 dark:text-blue-400">متوسط سعر الشراء:</span>
                    <span class="font-bold">{{ number_format($purchases->avg('unit_cost'), 2) }} ج.م</span>
                </div>
                <div>
                    <span class="text-blue-700 dark:text-blue-400">متوسط سعر البيع:</span>
                    <span class="font-bold">{{ number_format($sales->avg('unit_price'), 2) }} ج.م</span>
                </div>
                <div class="col-span-2">
                    @php
                        $avgPurchase = $purchases->avg('unit_cost');
                        $avgSale = $sales->avg('unit_price');
                        $margin = $avgSale > 0 ? (($avgSale - $avgPurchase) / $avgSale) * 100 : 0;
                    @endphp
                    <span class="text-blue-700 dark:text-blue-400">هامش الربح المتوسط:</span>
                    <span class="font-bold {{ $margin >= 25 ? 'text-green-600' : ($margin >= 15 ? 'text-yellow-600' : 'text-red-600') }}">
                        {{ number_format($margin, 1) }}%
                        @if($margin >= 25)
                            🟢 ممتاز
                        @elseif($margin >= 15)
                            🟡 جيد
                        @else
                            🔴 منخفض
                        @endif
                    </span>
                </div>
            </div>
        </div>
    @endif
</div>
