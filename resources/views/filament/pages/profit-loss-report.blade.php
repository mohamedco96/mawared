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
        @endphp

        <div class="mt-8 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <x-filament::section>
                    <x-slot name="heading">
                        إجمالي المبيعات
                    </x-slot>
                    <div class="text-3xl font-bold text-green-600">
                        {{ number_format($report['total_sales'], 2) }} ر.س
                    </div>
                    <div class="text-sm text-gray-500 mt-2">
                        عدد الفواتير: {{ $report['sales_count'] }}
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">
                        تكلفة البضاعة المباعة (COGS)
                    </x-slot>
                    <div class="text-3xl font-bold text-red-600">
                        {{ number_format($report['total_cogs'], 2) }} ر.س
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">
                        الربح الإجمالي
                    </x-slot>
                    <div class="text-3xl font-bold {{ $report['gross_profit'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ number_format($report['gross_profit'], 2) }} ر.س
                    </div>
                    <div class="text-sm text-gray-500 mt-2">
                        هامش الربح: {{ number_format($report['profit_margin'], 2) }}%
                    </div>
                </x-filament::section>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-filament::section>
                    <x-slot name="heading">
                        المصروفات
                    </x-slot>
                    <div class="text-2xl font-bold text-orange-600">
                        {{ number_format($report['expenses'], 2) }} ر.س
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">
                        صافي الربح
                    </x-slot>
                    <div class="text-3xl font-bold {{ $report['net_profit'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ number_format($report['net_profit'], 2) }} ر.س
                    </div>
                </x-filament::section>
            </div>
        </div>
    @endif
</x-filament-panels::page>
