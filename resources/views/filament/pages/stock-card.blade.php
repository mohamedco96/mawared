<x-filament-panels::page>
    <form wire:submit="generateReport">
        {{ $this->form }}

        <x-filament::button type="submit" class="mt-4">
            <x-heroicon-o-magnifying-glass class="w-4 h-4 mr-2"/>
            عرض التقرير
        </x-filament::button>
    </form>

    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('prefill-stock-card', (event) => {
                const productId = event[0]?.product_id || event.product_id;
                if (productId) {
                    @this.set('data.product_id', productId);
                }
            });
        });
    </script>

    @if ($this->reportData)
        @php
            $report = $this->reportData;
        @endphp

        <div class="mt-8" dir="rtl">
            {{-- Product Info Card --}}
            <div class="bg-white dark:bg-gray-900 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 p-6 mb-6">
                <h2 class="text-xl font-bold mb-4 text-gray-900 dark:text-white">معلومات المنتج</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <span class="text-gray-600 dark:text-gray-400">اسم المنتج: </span>
                        <span class="font-bold text-gray-900 dark:text-white">{{ $report['product']->name }}</span>
                    </div>
                    <div>
                        <span class="text-gray-600 dark:text-gray-400">المخزن: </span>
                        <span class="font-bold text-gray-900 dark:text-white">{{ $report['warehouse'] ? $report['warehouse']->name : 'جميع المخازن' }}</span>
                    </div>
                    <div>
                        <span class="text-gray-600 dark:text-gray-400">من تاريخ: </span>
                        <span class="font-bold text-gray-900 dark:text-white">{{ $report['from_date'] }}</span>
                    </div>
                    <div>
                        <span class="text-gray-600 dark:text-gray-400">إلى تاريخ: </span>
                        <span class="font-bold text-gray-900 dark:text-white">{{ $report['to_date'] }}</span>
                    </div>
                </div>
            </div>

            {{-- Stock Movements Table --}}
            <div style="margin-top: 20px !important; display: block; clear: both;" class="bg-white dark:bg-gray-900 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="bg-primary-600 text-white px-6 py-4">
                    <h2 class="text-xl font-bold">حركة المخزون</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 font-bold border-b border-gray-200 dark:border-gray-700">التاريخ</th>
                                <th class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 font-bold border-b border-gray-200 dark:border-gray-700">النوع</th>
                                <th class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 font-bold border-b border-gray-200 dark:border-gray-700">رقم المرجع</th>
                                <th class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 font-bold border-b border-gray-200 dark:border-gray-700">المخزن</th>
                                <th class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 font-bold border-b border-gray-200 dark:border-gray-700">وارد</th>
                                <th class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 font-bold border-b border-gray-200 dark:border-gray-700">صادر</th>
                                <th class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 font-bold border-b border-gray-200 dark:border-gray-700">التكلفة</th>
                                <th class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 font-bold border-b border-gray-200 dark:border-gray-700">الرصيد</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- Opening Stock --}}
                            <tr class="bg-yellow-50 dark:bg-yellow-900/20 border-b border-gray-200 dark:border-gray-700">
                                <td class="px-4 py-3 font-bold" colspan="4">رصيد أول المدة</td>
                                <td class="px-4 py-3 text-right text-gray-500">-</td>
                                <td class="px-4 py-3 text-right text-gray-500">-</td>
                                <td class="px-4 py-3 text-right text-gray-500">-</td>
                                <td class="px-4 py-3 text-right font-bold {{ $report['opening_stock'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ number_format($report['opening_stock'], 0) }}
                                </td>
                            </tr>

                            {{-- Stock Movements --}}
                            @forelse ($report['movements'] as $movement)
                                <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                    <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ $movement['date']->format('Y-m-d') }}</td>
                                    <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ $movement['type'] }}</td>
                                    <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ $movement['reference'] }}</td>
                                    <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ $movement['warehouse'] }}</td>
                                    <td class="px-4 py-3 text-right text-green-600 dark:text-green-400">
                                        {{ $movement['in'] > 0 ? number_format($movement['in'], 0) : '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-red-600 dark:text-red-400">
                                        {{ $movement['out'] > 0 ? number_format($movement['out'], 0) : '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">
                                        {{ number_format($movement['cost'], 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-bold {{ $movement['balance'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ number_format($movement['balance'], 0) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                        لا توجد حركات مخزون خلال الفترة المحددة
                                    </td>
                                </tr>
                            @endforelse

                            {{-- Totals Row --}}
                            <tr class="bg-gray-100 dark:bg-gray-800 font-bold">
                                <td class="px-4 py-3 text-gray-900 dark:text-white" colspan="4">الإجمالي</td>
                                <td class="px-4 py-3 text-right text-green-600 dark:text-green-400">{{ number_format($report['total_in'], 0) }}</td>
                                <td class="px-4 py-3 text-right text-red-600 dark:text-red-400">{{ number_format($report['total_out'], 0) }}</td>
                                <td class="px-4 py-3 text-right text-gray-900 dark:text-white">-</td>
                                <td class="px-4 py-3 text-right {{ $report['closing_stock'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ number_format($report['closing_stock'], 0) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
