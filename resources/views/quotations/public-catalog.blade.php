<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>عرض سعر - {{ $quotation->quotation_number }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-gray-50">

    <!-- Header Section -->
    <header class="bg-white shadow-sm sticky top-0 z-50">
        <div class="container mx-auto px-4 py-4 flex justify-between items-center">
            <!-- Company Logo -->
            @if($companySettings->logo)
                <img src="{{ Storage::url($companySettings->logo) }}"
                     alt="{{ $companySettings->company_name }}"
                     class="h-16 object-contain">
            @else
                <h1 class="text-2xl font-bold text-gray-800">
                    {{ $companySettings->company_name }}
                </h1>
            @endif

            <!-- Company Info -->
            <div class="text-left text-sm text-gray-600">
                <p>{{ $companySettings->company_phone }}</p>
                <p>{{ $companySettings->company_email }}</p>
            </div>
        </div>
    </header>

    <!-- Expiration Warning Banner -->
    @if($isExpired)
        <div class="bg-red-100 border-r-4 border-red-500 text-red-700 p-4 text-center">
            <p class="font-semibold">⚠️ انتهت صلاحية عرض السعر</p>
        </div>
    @endif

    <!-- Quotation Info Section -->
    <section class="bg-gradient-to-l from-blue-600 to-blue-700 text-white py-8">
        <div class="container mx-auto px-4">
            <div class="grid md:grid-cols-2 gap-6">
                <!-- Customer Info -->
                <div>
                    <h2 class="text-sm font-semibold mb-2 opacity-90">معلومات العميل</h2>
                    <p class="text-2xl font-bold">{{ $quotation->customer_name }}</p>
                    @if($quotation->customer_phone)
                        <p class="text-lg opacity-90">{{ $quotation->customer_phone }}</p>
                    @endif
                </div>

                <!-- Quotation Details -->
                <div class="text-left">
                    <h2 class="text-sm font-semibold mb-2 opacity-90">تفاصيل العرض</h2>
                    <p class="text-lg">رقم العرض: <span class="font-bold">{{ $quotation->quotation_number }}</span></p>
                    <p class="text-lg">التاريخ: {{ $quotation->created_at->format('Y-m-d') }}</p>
                    @if($quotation->valid_until)
                        <p class="text-lg">صالح حتى: {{ $quotation->valid_until->format('Y-m-d') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <!-- Products Grid (The Catalog) -->
    <section class="container mx-auto px-4 py-12">
        <h2 class="text-3xl font-bold text-gray-800 mb-8 text-center">
            الأصناف المعروضة
        </h2>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            @foreach($quotation->items as $item)
                <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-xl transition-shadow duration-300">

                    <!-- Product Image -->
                    <div class="aspect-square bg-gray-100 relative">
                        @if($item->product && $item->product->image)
                            <img src="{{ Storage::url($item->product->image) }}"
                                 alt="{{ $item->product_name }}"
                                 class="w-full h-full object-cover"
                                 loading="lazy">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-gray-400">
                                <svg class="w-20 h-20" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                        @endif

                        <!-- Quantity Badge -->
                        <div class="absolute top-3 left-3 bg-blue-600 text-white px-3 py-1 rounded-full text-sm font-semibold">
                            {{ $item->quantity }} {{ $item->unit_name }}
                        </div>
                    </div>

                    <!-- Product Info -->
                    <div class="p-5">
                        <h3 class="text-lg font-bold text-gray-800 mb-2 line-clamp-2">
                            {{ $item->product_name }}
                        </h3>

                        <div class="flex justify-between items-center mt-4">
                            <div>
                                <p class="text-sm text-gray-600">سعر الوحدة</p>
                                <p class="text-xl font-bold text-blue-600">
                                    {{ number_format($item->unit_price, 2) }} {{ $companySettings->currency_symbol }}
                                </p>
                            </div>

                            <div class="text-left">
                                <p class="text-sm text-gray-600">الإجمالي</p>
                                <p class="text-2xl font-bold text-gray-800">
                                    {{ number_format($item->total, 2) }} {{ $companySettings->currency_symbol }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <!-- Totals Section -->
    <section class="bg-white border-t-4 border-blue-600 py-8">
        <div class="container mx-auto px-4">
            <div class="max-w-md mr-auto">
                <div class="flex justify-between items-center text-lg mb-3">
                    <span class="text-gray-700">المجموع الفرعي:</span>
                    <span class="font-semibold">{{ number_format($quotation->subtotal, 2) }} {{ $companySettings->currency_symbol }}</span>
                </div>

                @if($quotation->discount > 0)
                    <div class="flex justify-between items-center text-lg mb-3 text-red-600">
                        <span>الخصم:</span>
                        <span class="font-semibold">- {{ number_format($quotation->discount, 2) }} {{ $companySettings->currency_symbol }}</span>
                    </div>
                @endif

                <div class="flex justify-between items-center text-2xl font-bold border-t-2 pt-3 text-blue-600">
                    <span>الإجمالي النهائي:</span>
                    <span>{{ number_format($quotation->total, 2) }} {{ $companySettings->currency_symbol }}</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Notes Section -->
    @if($quotation->notes)
        <section class="container mx-auto px-4 py-8">
            <div class="bg-yellow-50 border-r-4 border-yellow-400 p-6 rounded-lg">
                <h3 class="text-lg font-bold text-gray-800 mb-2">ملاحظات:</h3>
                <p class="text-gray-700 whitespace-pre-line">{{ $quotation->notes }}</p>
            </div>
        </section>
    @endif

    <!-- Floating Action Buttons -->
    <div class="fixed bottom-6 left-6 flex flex-col gap-3">
        <!-- Download PDF -->
        <a href="{{ route('quotations.public.pdf', $quotation->public_token) }}"
           class="bg-red-600 hover:bg-red-700 text-white rounded-full shadow-lg p-4 flex items-center gap-3 transition-all hover:scale-105"
           title="تحميل PDF">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <span class="font-semibold hidden sm:inline">تحميل PDF</span>
        </a>

        <!-- WhatsApp Contact -->
        <a href="{{ $quotation->getWhatsAppUrl() }}"
           target="_blank"
           class="bg-green-500 hover:bg-green-600 text-white rounded-full shadow-lg p-4 flex items-center gap-3 transition-all hover:scale-105"
           title="تواصل عبر واتساب">
            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
            </svg>
            <span class="font-semibold hidden sm:inline">واتساب</span>
        </a>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-6 mt-12">
        <div class="container mx-auto px-4 text-center">
            <p class="text-sm opacity-75">
                {{ $companySettings->company_name }} - جميع الحقوق محفوظة © {{ date('Y') }}
            </p>
            @if($companySettings->company_tax_number)
                <p class="text-xs opacity-60 mt-2">
                    الرقم الضريبي: {{ $companySettings->company_tax_number }}
                </p>
            @endif
        </div>
    </footer>

</body>
</html>
