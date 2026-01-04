<x-filament-panels::page>
    <form wire:submit="$dispatch('refreshTable')">
        {{ $this->form }}

        <x-filament::button type="submit" class="mt-4">
            <x-filament::icon icon="heroicon-o-magnifying-glass" class="h-5 w-5 ml-2" />
            عرض التقرير
        </x-filament::button>
    </form>

    <div class="mt-6">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
