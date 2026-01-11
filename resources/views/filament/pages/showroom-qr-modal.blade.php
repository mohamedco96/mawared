<div class="space-y-6" x-data="{ activeTab: 'retail' }">
    <!-- Tabs -->
    <div class="flex gap-2 border-b border-gray-200 dark:border-gray-700">
        <button
            type="button"
            @click="activeTab = 'retail'"
            :class="{
                'border-b-2 border-primary-600 text-primary-600': activeTab === 'retail',
                'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300': activeTab !== 'retail'
            }"
            class="px-4 py-2 font-semibold transition-colors"
        >
            كتالوج قطاعي
        </button>
        <button
            type="button"
            @click="activeTab = 'wholesale'"
            :class="{
                'border-b-2 border-primary-600 text-primary-600': activeTab === 'wholesale',
                'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300': activeTab !== 'wholesale'
            }"
            class="px-4 py-2 font-semibold transition-colors"
        >
            كتالوج الجملة
        </button>
    </div>

    <!-- Retail Tab -->
    <div x-show="activeTab === 'retail'" class="text-center space-y-4">
        <h3 class="text-lg font-bold text-gray-900 dark:text-white">
            رمز QR لكتالوج قطاعي
        </h3>
        <p class="text-sm text-gray-600 dark:text-gray-400">
            يعرض أسعار قطاعي فقط للعملاء
        </p>
        <div class="flex justify-center p-4 bg-white rounded-lg">
            {!! $retailQr !!}
        </div>
        <div class="flex gap-3 justify-center flex-wrap">
            <a href="{{ route('showroom.qr.download', 'retail') }}"
               class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-primary-600 hover:bg-primary-500 rounded-lg shadow transition-colors"
               download="catalog-retail-qr.svg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                تحميل SVG
            </a>
            <a href="{{ route('showroom.qr.download', 'retail') }}?format=png"
               class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-gray-600 hover:bg-gray-500 rounded-lg shadow transition-colors"
               download="catalog-retail-qr.png">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                تحميل PNG
            </a>
        </div>
        <div class="text-xs text-gray-500 dark:text-gray-400 font-mono bg-gray-100 dark:bg-gray-800 p-2 rounded break-all">
            {{ $retailUrl }}
        </div>
    </div>

    <!-- Wholesale Tab -->
    <div x-show="activeTab === 'wholesale'" class="text-center space-y-4">
        <h3 class="text-lg font-bold text-gray-900 dark:text-white">
            رمز QR لكتالوج الجملة
        </h3>
        <p class="text-sm text-gray-600 dark:text-gray-400">
            يعرض أسعار الجملة وقطاعي مع تفاصيل الوحدات الكبيرة
        </p>
        <div class="flex justify-center p-4 bg-white rounded-lg">
            {!! $wholesaleQr !!}
        </div>
        <div class="flex gap-3 justify-center flex-wrap">
            <a href="{{ route('showroom.qr.download', 'wholesale') }}"
               class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-primary-600 hover:bg-primary-500 rounded-lg shadow transition-colors"
               download="catalog-wholesale-qr.svg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                تحميل SVG
            </a>
            <a href="{{ route('showroom.qr.download', 'wholesale') }}?format=png"
               class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-gray-600 hover:bg-gray-500 rounded-lg shadow transition-colors"
               download="catalog-wholesale-qr.png">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                تحميل PNG
            </a>
        </div>
        <div class="text-xs text-gray-500 dark:text-gray-400 font-mono bg-gray-100 dark:bg-gray-800 p-2 rounded break-all">
            {{ $wholesaleUrl }}
        </div>
    </div>

    <!-- Instructions -->
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mt-6">
        <h4 class="font-semibold text-blue-900 dark:text-blue-300 mb-2">كيفية الاستخدام:</h4>
        <ol class="text-sm text-blue-800 dark:text-blue-400 space-y-1 list-decimal list-inside">
            <li>قم بتحميل رمز QR المناسب (SVG للطباعة، PNG للاستخدام الرقمي)</li>
            <li>اطبع الرمز ووزعه في المتجر أو استخدمه في المواد التسويقية</li>
            <li>عند مسح الرمز، سيتم توجيه العميل للكتالوج الرقمي</li>
            <li>يمكن للعملاء تصفح المنتجات وإضافتها لطلب واتساب مباشرة</li>
        </ol>
    </div>
</div>
