<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">إعدادات النظام</h2>
            <p class="text-gray-600 dark:text-gray-400">
                قم بإدارة إعدادات النظام من القائمة الجانبية.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <x-filament::card>
                <h3 class="text-lg font-semibold mb-2">المستخدمون</h3>
                <p class="text-gray-600 dark:text-gray-400 text-sm">
                    إدارة حسابات المستخدمين والصلاحيات
                </p>
            </x-filament::card>

            <x-filament::card>
                <h3 class="text-lg font-semibold mb-2">سجل النشاطات</h3>
                <p class="text-gray-600 dark:text-gray-400 text-sm">
                    متابعة جميع العمليات والتغييرات في النظام
                </p>
            </x-filament::card>

            <x-filament::card>
                <h3 class="text-lg font-semibold mb-2">الإعدادات العامة</h3>
                <p class="text-gray-600 dark:text-gray-400 text-sm">
                    تكوين الإعدادات الأساسية للتطبيق
                </p>
            </x-filament::card>

            <x-filament::card>
                <h3 class="text-lg font-semibold mb-2">النسخ الاحتياطي</h3>
                <p class="text-gray-600 dark:text-gray-400 text-sm">
                    إنشاء واستعادة نسخ احتياطية من قاعدة البيانات
                </p>
            </x-filament::card>
        </div>
    </div>
</x-filament-panels::page>
