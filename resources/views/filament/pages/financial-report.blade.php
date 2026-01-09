<x-filament-panels::page>
    <form wire:submit="generateReport">
        {{ $this->form }}

        <x-filament::button type="submit" class="mt-4">
            عرض التقرير
        </x-filament::button>
    </form>

    @if ($this->reportData)
        @php
            $report = $this->reportData;

            // Income Statement calculations
            // Note: Trade discounts are already included in invoice totals
            // Only settlement discounts (payment-time discounts) are added separately
            $incomeDebitTotal =
                $report['beginning_inventory'] +
                $report['total_purchases'] +
                $report['sales_returns'] +
                $report['expenses'] +
                $report['discount_allowed'];
            $incomeCreditTotal =
                $report['ending_inventory'] +
                $report['total_sales'] +
                $report['purchase_returns'] +
                $report['revenues'] +
                $report['discount_received'];
            $netProfit = $incomeCreditTotal - $incomeDebitTotal;

            // Financial Position calculations - CORRECT ACCOUNTING EQUATION
            // Assets = Liabilities + Equity
            $assetsTotal =
                $report['fixed_assets_value'] +
                $report['ending_inventory'] +
                $report['total_debtors'] +
                $report['total_cash'];

            // Equity = Capital + Net Profit - Drawings
            $equity = $report['shareholder_capital'] + $netProfit - $report['shareholder_drawings'];

            // Liabilities = Creditors only (NOT including Equity)
            $liabilitiesOnly = $report['total_creditors'];

            // Total Liabilities & Equity = Liabilities + Equity (for balance sheet equation)
            $liabilitiesTotal = $liabilitiesOnly + $equity;
        @endphp

        <div class="mt-8 space-y-16" dir="rtl">
            {{-- Section A: Income Statement (قائمة الدخل) --}}
            <div
                class="bg-white dark:bg-gray-900 rounded-lg shadow-lg dark:shadow-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="bg-primary-600 dark:bg-primary-600 text-white px-6 py-4">
                    <h2 class="text-xl font-bold">قائمة الدخل</h2>
                </div>

                <div
                    class="grid grid-cols-1 md:grid-cols-2 divide-x-0 md:divide-x divide-gray-200 dark:divide-gray-600 divide-x-reverse">
                    {{-- Right Column (مدين) --}}
                    <div class="p-6 dark:bg-gray-900">
                        <div class="mb-4 pb-2 border-b-2 border-gray-300 dark:border-primary-500">
                            <h3 class="text-lg font-bold text-gray-700 dark:text-white">مدين</h3>
                        </div>
                        <div class="space-y-3">
                            <div
                                class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-gray-700 dark:text-white">بضاعة أول المدة</span>
                                <span
                                    class="font-semibold text-gray-900 dark:text-white">{{ number_format($report['beginning_inventory'], 2) }}
                                    </span>
                            </div>
                            <div
                                class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-gray-700 dark:text-white">مشتريات</span>
                                <span
                                    class="font-semibold text-gray-900 dark:text-white">{{ number_format($report['total_purchases'], 2) }}
                                    </span>
                            </div>
                            <div
                                class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-gray-700 dark:text-white">مردودات مبيعات</span>
                                <span
                                    class="font-semibold text-gray-900 dark:text-white">{{ number_format($report['sales_returns'], 2) }}
                                    </span>
                            </div>
                            <div
                                class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-gray-700 dark:text-white">مصروفات</span>
                                <span
                                    class="font-semibold text-gray-900 dark:text-white">{{ number_format($report['expenses'], 2) }}
                                    </span>
                            </div>
                            <div
                                class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-gray-700 dark:text-white">خصم مسموح به (تسوية)</span>
                                <span
                                    class="font-semibold text-gray-900 dark:text-white">{{ number_format($report['discount_allowed'], 2) }}
                                    </span>
                            </div>
                            <div
                                class="flex justify-between items-center py-3 mt-4 pt-3 border-t-2 border-gray-400 dark:border-primary-500 bg-gray-50 dark:bg-gray-800 -mx-6 px-6">
                                <span class="text-lg font-bold text-gray-900 dark:text-white">الإجمالي</span>
                                <span
                                    class="text-lg font-bold text-gray-900 dark:text-white">{{ number_format($incomeDebitTotal, 2) }}
                                    </span>
                            </div>
                        </div>
                    </div>

                    {{-- Left Column (دائن) --}}
                    <div class="p-6 dark:bg-gray-900">
                        <div class="mb-4 pb-2 border-b-2 border-gray-300 dark:border-primary-500">
                            <h3 class="text-lg font-bold text-gray-700 dark:text-white">دائن</h3>
                        </div>
                        <div class="space-y-3">
                            <div
                                class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-gray-700 dark:text-white">بضاعة آخر المدة</span>
                                <span
                                    class="font-semibold text-gray-900 dark:text-white">{{ number_format($report['ending_inventory'], 2) }}
                                    </span>
                            </div>
                            <div
                                class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-gray-700 dark:text-white">مبيعات</span>
                                <span
                                    class="font-semibold text-gray-900 dark:text-white">{{ number_format($report['total_sales'], 2) }}
                                    </span>
                            </div>
                            <div
                                class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-gray-700 dark:text-white">مردودات مشتريات</span>
                                <span
                                    class="font-semibold text-gray-900 dark:text-white">{{ number_format($report['purchase_returns'], 2) }}
                                    </span>
                            </div>
                            <div
                                class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-gray-700 dark:text-white">إيرادات</span>
                                <span
                                    class="font-semibold text-gray-900 dark:text-white">{{ number_format($report['revenues'], 2) }}
                                    </span>
                            </div>
                            <div
                                class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-gray-700 dark:text-white">خصم مكتسب (تسوية)</span>
                                <span
                                    class="font-semibold text-gray-900 dark:text-white">{{ number_format($report['discount_received'], 2) }}
                                    </span>
                            </div>
                            <div
                                class="flex justify-between items-center py-3 mt-4 pt-3 border-t-2 border-gray-400 dark:border-primary-500 bg-gray-50 dark:bg-gray-800 -mx-6 px-6">
                                <span class="text-lg font-bold text-gray-900 dark:text-white">الإجمالي</span>
                                <span
                                    class="text-lg font-bold text-gray-900 dark:text-white">{{ number_format($incomeCreditTotal, 2) }}
                                    </span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Net Profit Footer --}}
                <div class="bg-gray-50 dark:bg-gray-800 px-6 py-4 border-t-2 border-gray-200 dark:border-primary-500">
                    <div class="flex justify-between items-center">
                        <span class="text-lg font-bold text-gray-900 dark:text-white">صافي الربح</span>
                        <span
                            class="text-xl font-bold {{ $netProfit >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                            {{ number_format($netProfit, 2) }}                        </span>
                    </div>
                </div>
            </div>

            {{-- Section B: Financial Position (المركز المالي) --}}
            <div style="margin-top: 20px !important; display: block; clear: both;"
                class="bg-white dark:bg-gray-900 rounded-lg shadow-lg dark:shadow-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="bg-primary-600 dark:bg-primary-600 text-white px-6 py-4">
                    <h2 class="text-xl font-bold">المركز المالي</h2>
                </div>

                <div
                    class="grid grid-cols-1 md:grid-cols-2 divide-x-0 md:divide-x divide-gray-200 dark:divide-gray-600 divide-x-reverse">
                    {{-- Right Column (أصول/مدين) --}}
                    <div class="p-6 dark:bg-gray-900">
                        <div class="mb-4 pb-2 border-b-2 border-gray-300 dark:border-primary-500">
                            <h3 class="text-lg font-bold text-gray-700 dark:text-white">أصول (مدين)</h3>
                        </div>
                        <div class="space-y-3">
                            <div
                                class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-gray-700 dark:text-white">أصول ثابتة</span>
                                <span
                                    class="font-semibold text-gray-900 dark:text-white">{{ number_format($report['fixed_assets_value'], 2) }}
                                    </span>
                            </div>
                            <div
                                class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-gray-700 dark:text-white">بضاعة آخر المدة</span>
                                <span
                                    class="font-semibold text-gray-900 dark:text-white">{{ number_format($report['ending_inventory'], 2) }}
                                    </span>
                            </div>
                            <div
                                class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-gray-700 dark:text-white">رصيد المدينين</span>
                                <span
                                    class="font-semibold text-gray-900 dark:text-white">{{ number_format($report['total_debtors'], 2) }}
                                    </span>
                            </div>
                            <div
                                class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-gray-700 dark:text-white">الخزينة والبنك</span>
                                <span
                                    class="font-semibold text-gray-900 dark:text-white">{{ number_format($report['total_cash'], 2) }}
                                    </span>
                            </div>
                            <div
                                class="flex justify-between items-center py-3 mt-4 pt-3 border-t-2 border-gray-400 dark:border-primary-500 bg-gray-50 dark:bg-gray-800 -mx-6 px-6">
                                <span class="text-lg font-bold text-gray-900 dark:text-white">إجمالي الأصول</span>
                                <span
                                    class="text-lg font-bold text-gray-900 dark:text-white">{{ number_format($assetsTotal, 2) }}
                                    </span>
                            </div>
                        </div>
                    </div>

                    {{-- Left Column (خصوم/دائن) --}}
                    <div class="p-6 dark:bg-gray-900">
                        <div class="mb-4 pb-2 border-b-2 border-gray-300 dark:border-primary-500">
                            <h3 class="text-lg font-bold text-gray-700 dark:text-white">خصوم (دائن)</h3>
                        </div>
                        <div class="space-y-3">
                            <div
                                class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-gray-700 dark:text-white">رأس مال الشركاء</span>
                                <span
                                    class="font-semibold text-gray-900 dark:text-white">{{ number_format($report['shareholder_capital'], 2) }}
                                    </span>
                            </div>
                            <div
                                class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-gray-700 dark:text-white">صافي الربح</span>
                                <span
                                    class="font-semibold {{ $netProfit >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ number_format($netProfit, 2) }}                                </span>
                            </div>
                            <div
                                class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-gray-700 dark:text-white">سحوبات الشركاء</span>
                                <span
                                    class="font-semibold text-red-600 dark:text-red-400">({{ number_format($report['shareholder_drawings'], 2) }})
                                    </span>
                            </div>
                            <div
                                class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 -mx-6 px-6">
                                <span class="text-gray-700 dark:text-white font-semibold">حقوق الملكية</span>
                                <span
                                    class="font-semibold text-gray-900 dark:text-white">{{ number_format($equity, 2) }}
                                    </span>
                            </div>
                            <div
                                class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-gray-700 dark:text-white">رصيد الدائنين</span>
                                <span
                                    class="font-semibold text-gray-900 dark:text-white">{{ number_format($report['total_creditors'], 2) }}
                                    </span>
                            </div>
                            <div
                                class="flex justify-between items-center py-3 mt-4 pt-3 border-t-2 border-gray-400 dark:border-primary-500 bg-gray-50 dark:bg-gray-800 -mx-6 px-6">
                                <span class="text-lg font-bold text-gray-900 dark:text-white">إجمالي الخصوم</span>
                                <span
                                    class="text-lg font-bold text-gray-900 dark:text-white">{{ number_format($liabilitiesTotal, 2) }}
                                    </span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Balance Footer --}}
                <div class="bg-gray-50 dark:bg-gray-800 px-6 py-4 border-t-2 border-gray-200 dark:border-primary-500">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex justify-between items-center">
                            <span class="text-lg font-bold text-gray-900 dark:text-white">إجمالي الأصول</span>
                            <span
                                class="text-xl font-bold text-gray-900 dark:text-white">{{ number_format($assetsTotal, 2) }}
                                </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-lg font-bold text-gray-900 dark:text-white">إجمالي الخصوم</span>
                            <span
                                class="text-xl font-bold text-gray-900 dark:text-white">{{ number_format($liabilitiesTotal, 2) }}
                                </span>
                        </div>
                    </div>
                    @if (abs($assetsTotal - $liabilitiesTotal) > 0.01)
                        <div class="mt-2 text-sm text-red-600 dark:text-red-400">
                            ملاحظة: يوجد فرق قدره {{ number_format(abs($assetsTotal - $liabilitiesTotal), 2) }}                        </div>
                    @else
                        <div class="mt-2 text-sm text-green-600 dark:text-green-400">
                            ✓ الميزانية متوازنة
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
