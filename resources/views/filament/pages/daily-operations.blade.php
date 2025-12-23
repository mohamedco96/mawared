<x-filament-panels::page>
    <x-filament::tabs>
        @php
            $activeTab = request()->get('tab', 'sales');
        @endphp
        @foreach($this->getTabs() as $key => $tab)
            <x-filament::tabs.item
                :active="$activeTab === $key"
                :badge="$tab->getBadge()"
                :badge-color="$tab->getBadgeColor()"
                :href="$this->getTabUrl($key)"
            >
                {{ $tab->getLabel() }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    <div class="mt-6">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
