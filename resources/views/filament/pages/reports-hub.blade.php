<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Report Type Selector - Tabs --}}
        <div class="overflow-hidden bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 rounded-xl">
            <div class="border-b border-gray-200 dark:border-gray-700">
                <nav class="flex flex-wrap gap-x-2 p-2" aria-label="Tabs" role="tablist">
                    @foreach($this->getReports() as $key => $report)
                        <button
                            wire:click="setActiveReport('{{ $key }}')"
                            type="button"
                            role="tab"
                            aria-selected="{{ $activeReport === $key ? 'true' : 'false' }}"
                            @class([
                                'flex items-center gap-x-2 px-4 py-3 text-sm font-semibold rounded-lg transition',
                                'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400' => $activeReport === $key,
                                'text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800' => $activeReport !== $key,
                            ])
                        >
                            <x-filament::icon
                                :icon="$report['icon']"
                                class="h-5 w-5"
                            />
                            <span>{{ $report['label'] }}</span>
                        </button>
                    @endforeach
                </nav>
            </div>

            {{-- Report Content Area --}}
            <div class="p-6">
                @foreach($this->getReports() as $key => $report)
                    <div
                        x-show="$wire.activeReport === '{{ $key }}'"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 transform scale-95"
                        x-transition:enter-end="opacity-100 transform scale-100"
                        class="space-y-4"
                    >
                        <div class="prose dark:prose-invert max-w-none">
                            @if($key === 'stock_card')
                                @livewire(\App\Filament\Pages\StockCard::class, key('stock-card'))
                            @elseif($key === 'partner_statement')
                                @livewire(\App\Filament\Pages\PartnerStatement::class, key('partner-statement'))
                            @elseif($key === 'profit_loss')
                                @livewire(\App\Filament\Pages\ProfitLossReport::class, key('profit-loss'))
                            @elseif($key === 'item_profitability')
                                @livewire(\App\Filament\Pages\ItemProfitabilityReport::class, key('item-profitability'))
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-filament-panels::page>
