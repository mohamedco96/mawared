<div class="space-y-4">
    @if($payment)
        <div class="grid grid-cols-2 gap-4">
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">المبلغ المدفوع</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    {{ number_format($payment->amount, 2) }}
                </p>
            </div>

            @if($payment->discount && $payment->discount > 0)
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">الخصم</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    {{ number_format($payment->discount, 2) }}
                </p>
            </div>
            @endif

            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">تاريخ الدفع</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    {{ $payment->payment_date->format('Y-m-d') }}
                </p>
            </div>

            @if($payment->partner)
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">العميل</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    {{ $payment->partner->name }}
                </p>
            </div>
            @endif

            @if($payment->creator)
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">تم الإنشاء بواسطة</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    {{ $payment->creator->name }}
                </p>
            </div>
            @endif

            @if($payment->treasuryTransaction)
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">رقم المعاملة المالية</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    #{{ $payment->treasuryTransaction->id }}
                </p>
            </div>
            @endif
        </div>

        @if($payment->notes)
        <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">ملاحظات</p>
            <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ $payment->notes }}</p>
        </div>
        @endif
    @else
        <div class="text-center py-8">
            <p class="text-gray-500 dark:text-gray-400">لا توجد بيانات للدفعة</p>
        </div>
    @endif
</div>
