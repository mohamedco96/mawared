<x-filament-panels::page>
    {{-- Render Widgets --}}
    @if ($headerWidgets = $this->getStatsWidgets())
        <x-filament-widgets::widgets :widgets="$headerWidgets" :columns="$this->getHeaderWidgetsColumns()" />
    @endif

    {{-- Render Tabs --}}
    <x-filament::tabs>
        @foreach ($this->getTabs() as $key => $tab)
            <x-filament::tabs.item :active="$this->activeTab === $key" :badge="$tab->badge" :badge-color="$tab->badgeColor" :icon="$tab->icon ?? null"
                wire:click="setActiveTab('{{ $key }}')" tag="button">
                {{ $tab->label }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    <div class="mt-6">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
