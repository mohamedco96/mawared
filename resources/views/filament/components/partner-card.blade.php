@props(['partner'])

@if ($partner)
    <div class="p-4 mt-2 bg-white border rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <!-- Partner Info -->
            <div class="flex-1">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <x-heroicon-m-user-circle class="w-6 h-6 text-primary-600" />
                    {{ $partner->name }}
                </h3>
                <div class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-400">
                    <div class="flex items-center gap-2">
                        <x-heroicon-m-phone class="w-4 h-4" />
                        <span dir="ltr">{{ $partner->phone ?? '—' }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-heroicon-m-map-pin class="w-4 h-4" />
                        <span>{{ $partner->region ?? 'العنوان غير محدد' }}</span>
                    </div>
                    @if ($partner->gov_id)
                        <div class="flex items-center gap-2">
                            <x-heroicon-m-identification class="w-4 h-4" />
                            <span>{{ $partner->gov_id }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Financial Status -->
            <div class="flex flex-col gap-3 min-w-[250px]">
                <!-- Balance -->
                <div @class([
                    'p-3 rounded-lg border',
                    'bg-red-50 border-red-100 text-red-700 dark:bg-red-900/20 dark:border-red-800 dark:text-red-400' =>
                        $partner->current_balance < 0,
                    'bg-green-50 border-green-100 text-green-700 dark:bg-green-900/20 dark:border-green-800 dark:text-green-400' =>
                        $partner->current_balance >= 0,
                ])>
                    <div class="text-xs font-medium opacity-80">الرصيد الحالي</div>
                    <div class="text-xl font-bold" dir="ltr">
                        {{ number_format(abs($partner->current_balance), 2) }}
                        <span
                            class="text-sm font-normal">{{ $partner->current_balance < 0 ? 'مدين (عليه)' : 'دائن (له)' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
@else
    <div
        class="p-4 mt-2 text-center text-gray-500 border border-dashed rounded-lg bg-gray-50 dark:bg-gray-800 dark:border-gray-700">
        الرجاء اختيار عميل لعرض البيانات
    </div>
@endif
