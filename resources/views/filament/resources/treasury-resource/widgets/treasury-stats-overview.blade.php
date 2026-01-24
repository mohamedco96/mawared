<x-filament-widgets::widget>
    <div
        class="fi-wi-stats-overview flex w-full gap-6 overflow-x-auto pb-2"
        style="scrollbar-width: thin;"
    >
        @foreach ($this->getCachedStats() as $stat)
            <div class="min-w-[250px] flex-shrink-0">
                {{ $stat }}
            </div>
        @endforeach
    </div>
</x-filament-widgets::widget>
