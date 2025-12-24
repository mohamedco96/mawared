<x-filament-panels::page>
    <form wire:submit="generateReport">
        {{ $this->form }}
        
        <x-filament::button type="submit" class="mt-4">
            عرض التقرير
        </x-filament::button>
    </form>

    @if($this->reportData)
        @php
            $report = $this->reportData;
            
            // Income Statement calculations
            $incomeDebitTotal = $report['beginning_inventory'] + $report['total_purchases'] + $report['sales_returns'] + $report['expenses'];
            $incomeCreditTotal = $report['ending_inventory'] + $report['total_sales'] + $report['purchase_returns'] + $report['revenues'];
            $netProfit = $incomeCreditTotal - $incomeDebitTotal;
            
            // Financial Position calculations
            $assetsTotal = $report['fixed_assets_value'] + $report['ending_inventory'] + $report['total_debtors'] + $report['total_cash'];
            $equity = $report['shareholder_capital'] + $netProfit - $report['shareholder_drawings'];
            $liabilitiesTotal = $equity + $report['total_creditors'];
        @endphp

        <div class="mt-8 space-y-8" dir="rtl">
            {{-- Section A: Income Statement (قائمة الدخل) --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-primary-600 text-white px-6 py-4">
                    <h2 class="text-xl font-bold">قائمة الدخل</h2>
                </div>
                
                <div class="grid grid-cols-2 divide-x divide-gray-200">
                    {{-- Right Column (مدين) --}}
                    <div class="p-6">
                        <div class="mb-4 pb-2 border-b border-gray-300">
                            <h3 class="text-lg font-semibold text-gray-700">مدين</h3>
                        </div>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <span class="text-gray-700">بضاعة أول المدة</span>
                                <span class="font-semibold text-gray-900">{{ number_format($report['beginning_inventory'], 2) }} ج.م</span>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <span class="text-gray-700">مشتريات</span>
                                <span class="font-semibold text-gray-900">{{ number_format($report['total_purchases'], 2) }} ج.م</span>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <span class="text-gray-700">مردودات مبيعات</span>
                                <span class="font-semibold text-gray-900">{{ number_format($report['sales_returns'], 2) }} ج.م</span>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <span class="text-gray-700">مصروفات</span>
                                <span class="font-semibold text-gray-900">{{ number_format($report['expenses'], 2) }} ج.م</span>
                            </div>
                            <div class="flex justify-between items-center py-3 mt-4 pt-3 border-t-2 border-gray-400">
                                <span class="text-lg font-bold text-gray-900">الإجمالي</span>
                                <span class="text-lg font-bold text-gray-900">{{ number_format($incomeDebitTotal, 2) }} ج.م</span>
                            </div>
                        </div>
                    </div>

                    {{-- Left Column (دائن) --}}
                    <div class="p-6">
                        <div class="mb-4 pb-2 border-b border-gray-300">
                            <h3 class="text-lg font-semibold text-gray-700">دائن</h3>
                        </div>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <span class="text-gray-700">بضاعة آخر المدة</span>
                                <span class="font-semibold text-gray-900">{{ number_format($report['ending_inventory'], 2) }} ج.م</span>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <span class="text-gray-700">مبيعات</span>
                                <span class="font-semibold text-gray-900">{{ number_format($report['total_sales'], 2) }} ج.م</span>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <span class="text-gray-700">مردودات مشتريات</span>
                                <span class="font-semibold text-gray-900">{{ number_format($report['purchase_returns'], 2) }} ج.م</span>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <span class="text-gray-700">إيرادات</span>
                                <span class="font-semibold text-gray-900">{{ number_format($report['revenues'], 2) }} ج.م</span>
                            </div>
                            <div class="flex justify-between items-center py-3 mt-4 pt-3 border-t-2 border-gray-400">
                                <span class="text-lg font-bold text-gray-900">الإجمالي</span>
                                <span class="text-lg font-bold text-gray-900">{{ number_format($incomeCreditTotal, 2) }} ج.م</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Net Profit Footer --}}
                <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
                    <div class="flex justify-between items-center">
                        <span class="text-lg font-bold text-gray-900">صافي الربح</span>
                        <span class="text-xl font-bold {{ $netProfit >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ number_format($netProfit, 2) }} ج.م
                        </span>
                    </div>
                </div>
            </div>

            {{-- Section B: Financial Position (المركز المالي) --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-primary-600 text-white px-6 py-4">
                    <h2 class="text-xl font-bold">المركز المالي</h2>
                </div>
                
                <div class="grid grid-cols-2 divide-x divide-gray-200">
                    {{-- Right Column (أصول/مدين) --}}
                    <div class="p-6">
                        <div class="mb-4 pb-2 border-b border-gray-300">
                            <h3 class="text-lg font-semibold text-gray-700">أصول (مدين)</h3>
                        </div>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <span class="text-gray-700">أصول ثابتة</span>
                                <span class="font-semibold text-gray-900">{{ number_format($report['fixed_assets_value'], 2) }} ج.م</span>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <span class="text-gray-700">بضاعة آخر المدة</span>
                                <span class="font-semibold text-gray-900">{{ number_format($report['ending_inventory'], 2) }} ج.م</span>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <span class="text-gray-700">رصيد المدينين</span>
                                <span class="font-semibold text-gray-900">{{ number_format($report['total_debtors'], 2) }} ج.م</span>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <span class="text-gray-700">الخزينة والبنك</span>
                                <span class="font-semibold text-gray-900">{{ number_format($report['total_cash'], 2) }} ج.م</span>
                            </div>
                            <div class="flex justify-between items-center py-3 mt-4 pt-3 border-t-2 border-gray-400">
                                <span class="text-lg font-bold text-gray-900">إجمالي الأصول</span>
                                <span class="text-lg font-bold text-gray-900">{{ number_format($assetsTotal, 2) }} ج.م</span>
                            </div>
                        </div>
                    </div>

                    {{-- Left Column (خصوم/دائن) --}}
                    <div class="p-6">
                        <div class="mb-4 pb-2 border-b border-gray-300">
                            <h3 class="text-lg font-semibold text-gray-700">خصوم (دائن)</h3>
                        </div>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <span class="text-gray-700">رأس مال الشركاء</span>
                                <span class="font-semibold text-gray-900">{{ number_format($report['shareholder_capital'], 2) }} ج.م</span>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <span class="text-gray-700">صافي الربح</span>
                                <span class="font-semibold {{ $netProfit >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ number_format($netProfit, 2) }} ج.م
                                </span>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <span class="text-gray-700">سحوبات الشركاء</span>
                                <span class="font-semibold text-red-600">({{ number_format($report['shareholder_drawings'], 2) }}) ج.م</span>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b border-gray-100 bg-gray-50">
                                <span class="text-gray-700 font-semibold">حقوق الملكية</span>
                                <span class="font-semibold text-gray-900">{{ number_format($equity, 2) }} ج.م</span>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <span class="text-gray-700">رصيد الدائنين</span>
                                <span class="font-semibold text-gray-900">{{ number_format($report['total_creditors'], 2) }} ج.م</span>
                            </div>
                            <div class="flex justify-between items-center py-3 mt-4 pt-3 border-t-2 border-gray-400">
                                <span class="text-lg font-bold text-gray-900">إجمالي الخصوم</span>
                                <span class="text-lg font-bold text-gray-900">{{ number_format($liabilitiesTotal, 2) }} ج.م</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Balance Footer --}}
                <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex justify-between items-center">
                            <span class="text-lg font-bold text-gray-900">إجمالي الأصول</span>
                            <span class="text-xl font-bold text-gray-900">{{ number_format($assetsTotal, 2) }} ج.م</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-lg font-bold text-gray-900">إجمالي الخصوم</span>
                            <span class="text-xl font-bold text-gray-900">{{ number_format($liabilitiesTotal, 2) }} ج.م</span>
                        </div>
                    </div>
                    @if(abs($assetsTotal - $liabilitiesTotal) > 0.01)
                        <div class="mt-2 text-sm text-red-600">
                            ملاحظة: يوجد فرق قدره {{ number_format(abs($assetsTotal - $liabilitiesTotal), 2) }} ج.م
                        </div>
                    @else
                        <div class="mt-2 text-sm text-green-600">
                            ✓ الميزانية متوازنة
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>

