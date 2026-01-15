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
            Livewire.on('prefill-partner-statement', (event) => {
                const partnerId = event[0]?.partner_id || event.partner_id;
                if (partnerId) {
                    @this.set('data.partner_id', partnerId);
                }
            });
        });
    </script>

    @if ($this->reportData)
        @php
            $report = $this->reportData;
        @endphp

        <div class="mt-8" dir="rtl">
            {{-- Partner Info Card --}}
            <div class="bg-white dark:bg-gray-900 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 p-6 mb-6">
                <h2 class="text-xl font-bold mb-4 text-gray-900 dark:text-white">معلومات العميل</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <span class="text-gray-600 dark:text-gray-400">الاسم:</span>
                        <span class="font-bold text-gray-900 dark:text-white mr-2">{{ $report['partner']->name }}</span>
                    </div>
                    <div>
                        <span class="text-gray-600 dark:text-gray-400">من تاريخ:</span>
                        <span class="font-bold text-gray-900 dark:text-white mr-2">{{ $report['from_date'] }}</span>
                    </div>
                    <div>
                        <span class="text-gray-600 dark:text-gray-400">إلى تاريخ:</span>
                        <span class="font-bold text-gray-900 dark:text-white mr-2">{{ $report['to_date'] }}</span>
                    </div>
                </div>
            </div>

            {{-- Transactions Table --}}
            <div style="margin-top: 20px !important; display: block; clear: both;" class="bg-white dark:bg-gray-900 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="bg-primary-600 text-white px-6 py-4">
                    <h2 class="text-xl font-bold">كشف الحساب</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 font-bold border-b border-gray-200 dark:border-gray-700">التاريخ</th>
                                <th class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 font-bold border-b border-gray-200 dark:border-gray-700">رقم المرجع</th>
                                <th class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 font-bold border-b border-gray-200 dark:border-gray-700">البيان</th>
                                <th class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 font-bold border-b border-gray-200 dark:border-gray-700">مدين</th>
                                <th class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 font-bold border-b border-gray-200 dark:border-gray-700">دائن</th>
                                <th class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 font-bold border-b border-gray-200 dark:border-gray-700">الرصيد</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- Opening Balance --}}
                            <tr class="bg-yellow-50 dark:bg-yellow-900/20 border-b border-gray-200 dark:border-gray-700">
                                <td class="px-4 py-3 font-bold" colspan="3">رصيد أول المدة</td>
                                <td class="px-4 py-3 text-right text-gray-500">-</td>
                                <td class="px-4 py-3 text-right text-gray-500">-</td>
                                <td class="px-4 py-3 text-right font-bold {{ $report['opening_balance'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ number_format($report['opening_balance'], 2) }}
                                </td>
                            </tr>

                            {{-- Transactions --}}
                            @forelse ($report['transactions'] as $transaction)
                                <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                    <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ $transaction['date']->format('Y-m-d') }}</td>
                                    <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ $transaction['reference'] }}</td>
                                    <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                                        {{ $transaction['description'] }}
                                        @if(isset($transaction['warehouse']))
                                            <span class="text-xs text-gray-500 dark:text-gray-400">({{ $transaction['warehouse'] }})</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">
                                        {{ $transaction['debit'] > 0 ? number_format($transaction['debit'], 2) : '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">
                                        {{ $transaction['credit'] > 0 ? number_format($transaction['credit'], 2) : '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-bold {{ $transaction['balance'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ number_format($transaction['balance'], 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                        لا توجد معاملات خلال الفترة المحددة
                                    </td>
                                </tr>
                            @endforelse

                            {{-- Totals Row --}}
                            <tr class="bg-gray-100 dark:bg-gray-800 font-bold">
                                <td class="px-4 py-3 text-gray-900 dark:text-white" colspan="3">الإجمالي</td>
                                <td class="px-4 py-3 text-right text-gray-900 dark:text-white">{{ number_format($report['total_debit'], 2) }}</td>
                                <td class="px-4 py-3 text-right text-gray-900 dark:text-white">{{ number_format($report['total_credit'], 2) }}</td>
                                <td class="px-4 py-3 text-right {{ $report['closing_balance'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ number_format($report['closing_balance'], 2) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
